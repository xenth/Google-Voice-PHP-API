<?php

require_once 'GoogleVoice.php';
$q = $_GET['q'];
$results = null;
$gv = null;
$function = '';

$parts = explode("\n", file_get_contents('credentials.txt'));
$gvPhone = $parts[2];

if (!empty($q)) {
  $gv = new GoogleVoice($parts[0], $parts[1]);
  switch ($q) {
    case 'search' :
      $number = $_GET['numberToSearch'];
      $results = $gv->searchNumber($number);
      $function = "searchNumber('$number')";
      break;
    case 'sendText' :
      $number = $_GET['number'];
      $message = $_GET['message'];
      $results = $gv->sendSMS($number, $message);
      $function = "sendSMS('$number', '$message')";
      break;
    case 'callNumber' :
      $results = $gv->callNumber($_GET['numberToCall'], $_GET['numberFrom']);
      $function = "callNumber('{$_GET['numberToCall']}', '{$_GET['numberFrom']}')";
      break;
    case 'getMissedCalls' :
      $results = $gv->getMissedCalls();
      $function = "getMissedCalls()";
      break;
    case 'getInbox' :
      $results = $gv->getInbox();
      $function = "getInbox()";
      break;
    case 'getSms' :
      switch ($_GET['scope']) {
        case 'new':
          $results = $gv->getNewSMS();
          $function = "getNewSMS()";
          break;
        case 'all':
          $results = $gv->getAllSMS();
          $function = "getAllSMS()";
          break;
        case 'read':
          $results = $gv->getReadSMS();
          $function = "getReadSMS()";
          break;
        case 'unread':
          $results = $gv->getUnreadSMS();
          $function = "getUnreadSMS()";
          break;
      }
      break;
    case 'getVoicemail' :
      switch ($_GET['scope']) {
        case 'all':
          $results = $gv->getAllVoicemail();
          $function = "getAllVoicemail()";
          break;
        case 'read':
          $results = $gv->getReadVoicemail();
          $function = "getRedVoicemail()";
          break;
        case 'unread':
          $results = $gv->getUnreadVoicemail();
          $function = "getUnreadVoicemail()";
          break;
      }
      break;
    case 'addNote' :
      $results = $gv->addNote($_GET['messageId'], $_GET['note']);
      $function = "addNote('{$_GET['messageId']}', '{$_GET['note']}')";
      break;
    case 'removeNote' :
      $results = $gv->removeNote($_GET['messageId']);
      $function = "removeNote('{$_GET['messageId']}')";
      break;
    case 'markRead' :
      $results = $gv->markRead($_GET['messageId']);
      $function = "markRead('{$_GET['messageId']}')";
      break;
    case 'markUnread' :
      $results = $gv->markUnread($_GET['messageId']);
      $function = "markUnread('{$_GET['messageId']}')";
      break;
    case 'archive' :
      $results = $gv->archive($_GET['messageId']);
      $function = "archive('{$_GET['messageId']}')";
      break;
    case 'unArchive' :
      $results = $gv->unArchive($_GET['messageId']);
      $function = "unArchive('{$_GET['messageId']}')";
      break;
    }
//    KLGHWKYHJOLVJZZJQWLVSZNHPVJWVQPWHWOXWTPL
//cancelCall($number, $from_number, $phone_type = 'mobile')
//getVoicemailMP3($messageId)
//deleteMessage($messageId)
}
?>
<table>
  <tr>
    <td>
      <h1>Get Voicemail</h1>
      <form method="get" action="gvTest.php">
        <input type="hidden" name="q" value="getVoicemail" />
        <input type="radio" name="scope" value="all" checked="checked"/>All<br/>
        <input type="radio" name="scope" value="read" />Read<br/>
        <input type="radio" name="scope" value="unread" />Unread<br/>
        <button type="submit">Get Voicemail</button>
      </form>
    </td>
    <td>
      <h1>Get Missed Calls</h1>
      <form method="get" action="gvTest.php">
        <input type="hidden" name="q" value="getMissedCalls" />
        <button type="submit">Get Missed Calls</button>
      </form>
    </td>
    <td>
      <h1>Get Inbox</h1>
      <form method="get" action="gvTest.php">
        <input type="hidden" name="q" value="getInbox" />
        <button type="submit">Get Inbox</button>
      </form>
    </td>
  </tr>
  <tr>
    <td>
      <h1>Search for a number</h1>
      <form method="get" action="gvTest.php">
        <input type="hidden" name="q" value="search" />
        <label for="numberToSearch">Search for Telephone number:</label>
        <input type="tel" name="numberToSearch"/>
        <br />
        <button type="submit">Search</button>
      </form>
    </td>
    <td>
      <h1>Send a text</h1>
      <form method="get" action="gvTest.php">
        <input type="hidden" name="q" value="sendText" />
        <label for="number">Number to text:</label>
        <input type="tel" name="number"/>
        <br />
        <label for="message">Message:</label>
        <input type="text" name="message"/>
        <br />
        <button type="submit">Send Text</button>
      </form>
    </td>
    <td>
      <h1>Call Number</h1>
      <form method="get" action="gvTest.php">
        <input type="hidden" name="q" value="callNumber" />
        <label for="numberToCall">Number to Call:</label>
        <input type="tel" name="numberToCall"/>
        <br />
        <label for="numberFrom">Number to Call From:</label>
        <input type="tel" name="numberFrom" value="<?=$gvPhone;?>" />
        <br />
        <button type="submit">Connect</button>
      </form>
    </td>
  </tr>
  <tr>
    <td>
      <h1>Add a note</h1>
      <form method="get" action="gvTest.php">
        <input type="hidden" name="q" value="addNote" />
        <label for="messageId">Message ID:</label>
        <input type="text" name="messageId"/>
        <br />
        <label for="note">Note:</label>
        <input type="text" name="note"/>
        <br />
        <button type="submit">Add Note</button>
      </form>
    </td>
    <td>
      <h1>Remove Note</h1>
      <form method="get" action="gvTest.php">
        <input type="hidden" name="q" value="removeNote" />
        <label for="messageId">Message ID:</label>
        <input type="text" name="messageId"/>
        <br />
        <button type="submit">Remove Note</button>
      </form>
    </td>
    <td>
      <h1>Get SMS</h1>
      <form method="get" action="gvTest.php">
        <input type="hidden" name="q" value="getSms" />
        <input type="radio" name="scope" value="new" checked="checked"/>New<br/>
        <input type="radio" name="scope" value="all" />All<br/>
        <input type="radio" name="scope" value="read" />Read<br/>
        <input type="radio" name="scope" value="unread" />Unread<br/>
        <button type="submit">Get SMS</button>
      </form>
    </td>
    <td>
    </td>
  </tr>
  <tr>
    <td>
      <h1>Mark Message as Read</h1>
      <form method="get" action="gvTest.php">
        <input type="hidden" name="q" value="markRead" />
        <label for="messageId">Message ID:</label>
        <input type="text" name="messageId"/>
        <br />
        <button type="submit">Mark Read</button>
      </form>
    </td>
    <td>
      <h1>Mark Message as Unread</h1>
      <form method="get" action="gvTest.php">
        <input type="hidden" name="q" value="markUnread" />
        <label for="messageId">Message ID:</label>
        <input type="text" name="messageId"/>
        <br />
        <button type="submit">Mark Unread</button>
      </form>
    </td>
    <td>
      <h1>Archive Message</h1>
      <form method="get" action="gvTest.php">
        <input type="hidden" name="q" value="archive" />
        <label for="messageId">Message ID:</label>
        <input type="text" name="messageId"/>
        <br />
        <button type="submit">Archive</button>
      </form>
    </td>
  </tr>
  <tr>
    <td>
      <h1>Un-Archive Message</h1>
      <form method="get" action="gvTest.php">
        <input type="hidden" name="q" value="unArchive" />
        <label for="messageId">Message ID:</label>
        <input type="text" name="messageId"/>
        <br />
        <button type="submit">Un-Archive</button>
      </form>
    </td>
    <td>
    </td>
    <td>
    </td>
  <tr>
    <td>
      <p>
        These functions are not implemneted in the test suite yet:
      </p>
      <pre>
        cancelCall($number, $from_number, $phone_type = 'mobile')
        getVoicemailMP3($messageId)
        deleteMessage($messageId)
      </pre>
    </td>
  </tr>
</table>

<div id="results" style="border: 2px #000000 solid">
  <h1>Results from actions</h1>
<?php
if ($function) {
  echo "<h2>Function used to obtain results: GoogleVoice::$function</h2>";
}

if ($results ===  null) {
  echo '<h2>No results</h2>';
} else {
  echo '<pre>'.print_r($results, true).'</pre>';
}
?>
</div>
<div id="results" style="border: 2px #000000 solid">
  <h1>Results gv login</h1>
<?php
if ($gv ===  null) {
  echo '<h2>No results</h2>';
} else {
  $vals = $gv->getVals();
  echo '<pre>'
    ."Curl Url: $vals->curlUrl\n"
    ."Curl Options: ".print_r($vals->curlOptions, true)."\n"
    ."Raw server result: ".print_r(htmlspecialchars($vals->result), true);
//  echo $gv->getGvResults('loginChallengeResult');
//  echo $gv->getGvResults('loginRequestResult');
//  echo 'Results display disabled.';
}
?>
</div>
