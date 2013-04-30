<?php
    require_once "libs/facebook/src/base_facebook.php";
    
    class ApiFacebook extends BaseFacebook {
        protected function setPersistentData($key, $value) {
          self::errorLog('Persistent data not supported by API-only interface');
        }

        protected function getPersistentData($key, $default = false) {
          self::errorLog('Persistent data not supported by API-only interface');
        }

        protected function clearPersistentData($key) {
          self::errorLog('Persistent data not supported by API-only interface');
        }

        protected function clearAllPersistentData() {
          self::errorLog('Persistent data not supported by API-only interface');
        }  
    }
?>
