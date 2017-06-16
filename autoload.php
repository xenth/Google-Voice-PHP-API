<?php
/*
 * Copyright 2014 Google Inc.
 *
 * I don't understand autoloaders very well, so this is a copy of the autoloader
 * from the google service php api
 * It is much simplified since this project has one class.
 * 
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

function google_voice_php_api_autoload($className)
{
  // This project has one class, and it is named GoogleVoice
  if ($className === 'GoogleVoice') {
    $filePath = dirname(__FILE__) . '/' . $className . '.php';
    if (file_exists($filePath)) {
      require_once($filePath);
    }
  }
}

spl_autoload_register('google_voice_php_api_autoload');
