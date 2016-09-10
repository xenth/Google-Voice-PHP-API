<?php

class GoogleVoice {
  const GV_SERVER_URL = 'https://www.google.com/voice';

  // Google account credentials.
	private $_login;
	private $_pass;

	// Special string that Google Voice requires in our POST requests.
	private $_rnr_se;

	// File handle for PHP-Curl.
	private $_ch;
  private $_curlUrl;
  private $_curlOptions;

	// The location of our cookies.
	private $_cookieFile;

	// Are we logged in already?
	private $_loggedIn = FALSE;

  // The result returned from the google server
  private $_result;
  
	private $_phoneTypes = array(
    'mobile' => 2,
    'work' => 3,
    'home' => 1
  );
  
	private $_serverPath = array(
    'addNote' => '/inbox/savenote/',
    'archive' => '/inbox/archiveMessages/',
    'call' => '/call/connect/',
		'cancel' => '/call/cancel/',
    'delete' => '/inbox/deleteMessages/',
    'deleteNote' => '/inbox/deletenote/',
    'getMP3' => '/media/send_voicemail/',
    'getSMS' => '/inbox/recent/sms/',
    'voicemail' => '/inbox/recent/voicemail/',
    'inbox' => '/inbox/recent/',
    'mark' => '/inbox/mark/',
    'missed' => '/inbox/recent/missed/',
    'sendSMS' => '/sms/send/',
    'voicemail' => '/inbox/recent/voicemail/'
  );
  
  public function __construct($login, $pass) {
		$this->_login = $login;
		$this->_pass = $pass;
		$this->_cookieFile = '/tmp/gvCookies.txt';

		$this->_ch = curl_init();
		curl_setopt($this->_ch, CURLOPT_COOKIEJAR, $this->_cookieFile);
		curl_setopt($this->_ch, CURLOPT_FOLLOWLOCATION, TRUE);
		curl_setopt($this->_ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($this->_ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; WOW64; Trident/7.0; rv:11.0) like Gecko");  //was "Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.0)"
	}

  private function _formatNumber($number)
  {
    $newNumber = '';
    $numChars = strlen($number);

    // Remove all characters except numbers
    for ($i = 0; $i < $numChars; $i += 1) {
      $theChar = substr($number, $i, 1);
      if (stripos('0123456789', $theChar) !== false) {
        $newNumber .= $theChar;
      }
    }

    // Make sure the mumber begins with '+1'
    if (substr($newNumber, 0, 1) === '1') {
      $newNumber = '+' . $newNumber;
    } else {
      $newNumber = '+1' . $newNumber;
    }

    return $newNumber;
  }

  private function _get($path)
  {
    // Login to the service if not already done.
    $this->_logIn();

    // @TODO we can access beyond page 1 by adding ?page=pX where X is the number
    // of the page requested
    $this->_curlUrl = self::GV_SERVER_URL . $path;
    
    // Send HTTP POST request.
    curl_setopt($this->_ch, CURLOPT_URL, $this->_curlUrl);
    curl_setopt($this->_ch, CURLOPT_POST, false);
    curl_setopt($this->_ch, CURLOPT_RETURNTRANSFER, true);

    $this->_result = curl_exec($this->_ch);
    
    return $this->_result;
  }

  private function _getAndParse($path, $isRead = null)
  {
    return $this->_parseGetResults($this->_get($path), $isRead);
  }

	private function _logIn() {
		global $conf;

		if($this->_loggedIn)
			return TRUE;

		// Fetch the Google Voice login page input fields
		$URL = "https://accounts.google.com/ServiceLogin?"
      ."service=grandcentral&"
      ."passive=1209600&"
      ."continue=".self::GV_SERVER_URL."&"
      ."followup=".self::GV_SERVER_URL."&"
      ."ltmpl=open";  //adding login to GET prefills with username "&Email=$this->_login"
		curl_setopt($this->_ch, CURLOPT_URL, $URL);
		$html = curl_exec($this->_ch);

		// Send HTTP POST service login request using captured input information.
    // This is the second page of the two page signin
		$URL='https://accounts.google.com/signin/challenge/sl/password';
		curl_setopt($this->_ch, CURLOPT_URL, $URL);
    // Using DOM keeps the order of the name/value from breaking the code.
  	$postarray = $this->dom_get_input_tags($html);

		// Parse the returned webpage for the "GALX" token, needed for POST requests.
		if(!isset($postarray['GALX']) || $postarray['GALX']==''){
			$pi1 = var_export($postarray, TRUE);
			error_log("Could not parse for GALX token. Inputs from page:\n" . $pi1 . "\n\nHTML from page:" . $html);
			throw new Exception("Could not parse for GALX token. Inputs from page:\n" . $pi1);
		}

		$postarray['Email'] = $this->_login;  //Add login to POST array
		$postarray['Passwd'] = $this->_pass;  //Add password to POST array
		curl_setopt($this->_ch, CURLOPT_POST, TRUE);
		curl_setopt($this->_ch, CURLOPT_POSTFIELDS, $postarray);
		$html = curl_exec($this->_ch);

		// Test if the service login was successful.
    // Using DOM keeps the order of the name/value from breaking the code.
		$postarray = $this->dom_get_input_tags($html);
		if(isset($postarray['_rnr_se']) && $postarray['_rnr_se']!='') {
			$this->_rnr_se = $postarray['_rnr_se'];
			$this->_loggedIn = TRUE;
		} else {
			$pi2 = var_export($postarray, TRUE);
			error_log("Could not log in to Google Voice with username: " . $this->_login .
					  "\n\nMay need to change scraping.  Here are the inputs from the page:\n". $pi2
					 );  //add POST action information from DOM.  May help hunt down single or dual sign on page changes.
			throw new Exception("Could not log in to Google Voice with username: " . $this->_login . "\nLook at error log for detailed input information.\n");
		}
	}

  private function _parseGetResults($xml, $isRead = null)
  {
    // Load the "wrapper" xml (contains two elements, json and html).
    $dom = new \DOMDocument();
    $dom->loadXML($xml);
    $json = $dom->documentElement->getElementsByTagName("json")->item(0)->nodeValue;
    $json = json_decode($json);
    // $json->resultsPerPage shows how many messages are returned per page
    // $json->unreadCount an array of messages labels and the number of unread messages

    // Loop through all of the messages.
    $results = array();
    foreach ($json->messages as $mid => $convo) {
      // This is what I had:
      if ($isRead !== null) {
        if ($convo->isRead == $isRead) {
          $results[] = $convo;
        }
      } else {
        $results[] = $convo;
      }
    }

    return $results;
  }

  /**
    * Communicate with Google voice server using post
    * @param $path Path appended to the google voice server url
    * @param $options Required options for the specified path
    */
  private function _post($path, $options)
  {
    // Login to the service if not already done.
    $this->_logIn();

    $options['_rnr_se'] = $this->_rnr_se;
    $this->_curlUrl = self::GV_SERVER_URL . $path;
    $this->_curlOptions = $options;
    // Send HTTP POST request.
    curl_setopt($this->_ch, CURLOPT_URL, $this->_curlUrl);
    curl_setopt($this->_ch, CURLOPT_POST, true);
    curl_setopt($this->_ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($this->_ch, CURLOPT_POSTFIELDS, $options);

    $this->_result = curl_exec($this->_ch);
    
    return $this->_result;
  }

  private function _verifyPhoneType($type)
  {
    // Make sure phone type is set properly.
    if (!array_key_exists($type, $this->_phoneTypes)) {
      throw new \Exception('Phone type must be mobile, work, or home');
    }
  }

  public function getVals()
  {
    return (object) array(
      'curlUrl' => $this->_curlUrl,
      'curlOptions' => $this->_curlOptions,
      'result' => $this->_result
    );
  }
   /**
    * Mark a message in a Google Voice Inbox as archived.
    * @param $messageId The id of the message to update.
    */
  public function archive($messageId)
  {
    return $this->_post(
      $this->_serverPath['archive'],
      array(
        'messages' => $messageId,
        'archive' => '1',
        'read' => '1'
      )
    );
  }

   /**
    * Mark a message in a Google Voice Inbox as unarchived.
    * @param $messageId The id of the message to update.
    */
  public function unArchive($messageId)
  {
    $this->_post(
      $this->_serverPath['archive'],
      array(
        'messages' => $messageId,
        'archive' => '0'
      )
    );
  }

	/**
	 * Place a call to $number connecting first to $fromNumber.
	 * @param $number The 10-digit phone number to call (formatted with parens and hyphens or none).
	 * @param $fromNumber The 10-digit number on your account to connect the call (no hyphens or spaces).
	 * @param $phoneType (mobile, work, home) The type of phone the $fromNumber is. The call will not be connected without this value.
	 */
	public function callNumber($number, $from_number, $phone_type = 'mobile') {
    $this->_verifyPhoneType($phone_type);

		// Send HTTP POST request.
    return $this->_post(
      $this->_serverPath['call'],
      array(
        'forwardingNumber' => $this->_formatNumber($from_number),
        'outgoingNumber' => $this->_formatNumber($number),
        'phoneType' => $this->_phoneTypes[$phone_type],
        'remember' => '0',
        'subscriberNumber' => 'undefined'
      )
    );
	}

	/**
	 * Cancel a call to $number connecting first to $fromNumber.
	 * @param $number The 10-digit phone number to call (formatted with parens and hyphens or none).
	 * @param $fromNumber The 10-digit number on your account to connect the call (no hyphens or spaces).
	 * @param $phoneType (mobile, work, home) The type of phone the $fromNumber is. The call will not be connected without this value.
	 */
	public function cancelCall($number, $from_number, $phone_type = 'mobile') {
    $this->_verifyPhoneType($phone_type);

    return $this->_post(
      $this->_serverPath['cancel'],
      array(
        'forwardingNumber' => $this->_formatNumber($from_number),
        'outgoingNumber' => $this->_formatNumber($number),
        'phoneType' => $this->_phoneTypes[$phone_type],
        'remember' => 0,
        'subscriberNumber' => 'undefined'
      )
    );
	}

	/**
	 * Send an SMS to $number containing $message.
	 * @param $number The 10-digit phone number to send the message to (formatted with parens and hyphens or none).
	 * @param $message The message to send within the SMS.
	 */
	public function sendSMS($number, $message) {
    return $this->_post(
      $this->_serverPath['sendSMS'],
      array(
        'phoneNumber' => $this->_formatNumber($number),
        'text' => $message
      )
    );
	}
  
	/**
	 * Get all of the SMS messages in a Google Voice inbox.
	 */
	public function getAllSMS()
	{
    // isRead = false
    return $this->_getAndParse($this->_serverPath['getSMS'], null);
	}

   /**
    * Get all calls in the Google Voice inbox.
    */
  public function getInbox()
  {
    return $this->_getAndParse($this->_serverPath['inbox'], null);
  }
   
   /**
    * Get all of the missed calls in a Google Voice inbox.
    */
  public function getMissedCalls()
  {
    return $this->_getAndParse($this->_serverPath['missed'], null);
  }
   
	/**
	 * Get all of the unread SMS messages in a Google Voice inbox.
	 */
	public function getNewSMS()
	{
    $xml = $this->_get($this->_serverPath['getSMS']);

    // Load the "wrapper" xml (contains two elements, json and html).
    $dom = new \DOMDocument();
    $dom->loadXML($xml);
    $json = $dom->documentElement->getElementsByTagName("json")->item(0)->nodeValue;
    $json = json_decode($json);

		// now make a dom parser which can parse the contents of the HTML tag
		$html = $dom->documentElement->getElementsByTagName("html")->item(0)->nodeValue;
		// replace all "&" with "&amp;" so it can be parsed
		$html = str_replace("&", "&amp;", $html);
		$dom->loadHTML($html);
		$xpath = new DOMXPath($dom);

    // Loop through all of the messages.
    $results = array();
    foreach ($json->messages as $mid => $convo) {
      // This is what xenth has:
			$elements = $xpath->query("//div[@id='$mid']//div[@class='gc-message-sms-row']");
			if(!is_null($elements)) {
				if( in_array('unread', $convo->labels) ) {
					foreach($elements as $i=>$element) {
						$XMsgFrom = $xpath->query("span[@class='gc-message-sms-from']", $element);
						$msgFrom = '';
						foreach($XMsgFrom as $m) {
							$msgFrom = trim($m->nodeValue);
            }

						if( $msgFrom != "Me:" ) {
							$XMsgText = $xpath->query("span[@class='gc-message-sms-text']", $element);
							$msgText = '';
							foreach($XMsgText as $m) {
								$msgText = trim($m->nodeValue);
              }

							$XMsgTime = $xpath->query("span[@class='gc-message-sms-time']", $element);
							$msgTime = '';
							foreach($XMsgTime as $m) {
								$msgTime = trim($m->nodeValue);
              }

							$results[] = array('msgID'=>$mid, 'phoneNumber'=>$convo->phoneNumber, 'message'=>$msgText, 'date'=>date('Y-m-d H:i:s', strtotime(date('m/d/Y ',intval($convo->startTime/1000)).$msgTime)));
						}
					}
				} else {
					//echo "This message is not unread\n";
				}
			} else {
				//echo "gc-message-sms-row query failed\n";
			}
      
    }

    return $results;
	}

	/**
	 * Get all of the unread SMS messages in a Google Voice inbox.
	 */
	public function getUnreadSMS() {
    // isRead = false
    return $this->_getAndParse($this->_serverPath['getSMS'], false);
	}
	/**
	 * Get all of the read SMS messages in a Google Voice inbox.
	 */
	public function getReadSMS()
	{
    // isRead = false
    return $this->_getAndParse($this->_serverPath['getSMS'], true);
	}

	/**
	 * Mark a message in a Google Voice Inbox or Voicemail as read.
	 * @param $messageId The id of the message to update.
	 * @param $note The message to send within the SMS.
	 */
	public function markRead($messageId) {
    $this->_post(
      $this->_serverPath['mark'],
      array(
        'messages' => $messageId,
        'read' => '1'
      )
    );
	}

	/**
	 * Mark a message in a Google Voice Inbox or Voicemail as unread.
	 * @param $messageId The id of the message to update.
	 * @param $note The message to send within the SMS.
	 */
	public function markUnread($messageId) {
    $this->_post(
      $this->_serverPath['mark'],
      array(
        'messages' => $messageId,
        'read' => '0'
      )
    );
	}

	/**
	 * Add a note to a message in a Google Voice Inbox or Voicemail.
	 * @param $messageId The id of the message to update.
	 * @param $note The message to send within the SMS.
	 */
	public function addNote($messageId, $note) {
    return $this->_post(
      $this->_serverPath['addNote'],
      array(
        'id' => $messageId,
        'note' => $note
      )
    );
	}

	/**
	 * Removes a note from a message in a Google Voice Inbox or Voicemail.
	 * @param $messageId The id of the message to update.
	 */
	public function removeNote($messageId) {
    return $this->_post(
      '/inbox/deletenote/',
      array(
        'id' => $messageId
      )
    );
	}

	public function getAllVoicemail()
  {
    return $this->_getAndParse($this->_serverPath['voicemail'], null);
  }
  
	/**
	 * Get all of the unread SMS messages from a Google Voice Voicemail.
	 */
	public function getUnreadVoicemail()
  {
    return $this->_getAndParse($this->_serverPath['voicemail'], false);
	}

	/**
	 * Get all of the unread SMS messages from a Google Voice Voicemail.
	 */
	public function getReadVoicemail() {
    return $this->_getAndParse($this->_serverPath['voicemail'], true);
	}

	/**
	 * Get MP3 of a Google Voice Voicemail.
	 */
	public function getVoicemailMP3($messageId) {
    return $this->_get($this->_serverPath['getMP3'] . $messageId . '/');
	}

	/**
	 * Delete a message or conversation.
	 * @param $messageId The ID of the conversation to delete.
	 */
	public function deleteMessage($messageId) {
    $this->_post(
      $this->_serverPath['delete'],
      array(
        'messages' => $messageId,
        'trash' => 1
      )
    );
	}

	public function dom_dump($obj) {
		if ($classname = get_class($obj)) {
			$retval = "Instance of $classname, node list: \n";
			switch (TRUE) {
				case ($obj instanceof DOMDocument):
					$retval .= "XPath: {$obj->getNodePath()}\n".$obj->saveXML($obj);
					break;
				case ($obj instanceof DOMElement):
					$retval .= "XPath: {$obj->getNodePath()}\n".$obj->ownerDocument->saveXML($obj);
					break;
				case ($obj instanceof DOMAttr):
					$retval .= "XPath: {$obj->getNodePath()}\n".$obj->ownerDocument->saveXML($obj);
					break;
				case ($obj instanceof DOMNodeList):
					for ($i = 0; $i < $obj->length; $i++) {
						$retval .= "Item #$i, XPath: {$obj->item($i)->getNodePath()}\n"."{$obj->item($i)->ownerDocument->saveXML($obj->item($i))}\n";
					}
					break;
				default:
					return "Instance of unknown class";
			}
		}
		else {
			return 'no elements...';
		}
		return htmlspecialchars($retval);
	}

	/**
	 * Source from http://www.binarytides.com/php-get-name-and-value-of-all-input-tags-on-a-page-with-domdocument/
	 * Generic function to fetch all input tags (name and value) on a page
	 * Useful when writing automatic login bots/scrapers
	 */
	private function dom_get_input_tags($html)
	{
	    $post_data = array();

	    // a new dom object
	    $dom = new DomDocument;

	    //load the html into the object
	    @$dom->loadHTML($html);  //@suppresses warnings
	    //discard white space
	    $dom->preserveWhiteSpace = FALSE;

	    //all input tags as a list
	    $input_tags = $dom->getElementsByTagName('input');

	    //get all rows from the table
	    for ($i = 0; $i < $input_tags->length; $i++)
	    {
	        if( is_object($input_tags->item($i)) )
	        {
	            $name = $value = '';
	            $name_o = $input_tags->item($i)->attributes->getNamedItem('name');
	            if(is_object($name_o))
	            {
	                $name = $name_o->value;

	                $value_o = $input_tags->item($i)->attributes->getNamedItem('value');
	                if(is_object($value_o))
	                {
	                    $value = $input_tags->item($i)->attributes->getNamedItem('value')->value;
	                }

	                $post_data[$name] = $value;
	            }
	        }
	    }

	    return $post_data;
	}

}
