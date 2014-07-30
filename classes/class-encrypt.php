<?php
function aes128_cbc_encrypt($key, $data, $iv) {
  if(16 !== strlen($key)) $key = hash('MD5', $key, true);
  if(16 !== strlen($iv)) $iv = hash('MD5', $iv, true);
  $padding = 16 - (strlen($data) % 16);
  $data .= str_repeat(chr($padding), $padding);
  return base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, $data, MCRYPT_MODE_CBC, $iv));
}
 
function aes256_cbc_encrypt($key, $data, $iv) {
  if(32 !== strlen($key)) $key = hash('SHA256', $key, true);
  if(16 !== strlen($iv)) $iv = hash('MD5', $iv, true);
  $padding = 16 - (strlen($data) % 16);
  $data .= str_repeat(chr($padding), $padding);
  return base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, $data, MCRYPT_MODE_CBC, $iv));
}
 
function aes128_cbc_decrypt($key, $data, $iv) {
  if(16 !== strlen($key)) $key = hash('MD5', $key, true);
  if(16 !== strlen($iv)) $iv = hash('MD5', $iv, true);
  $data = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, base64_decode($data), MCRYPT_MODE_CBC, $iv);
  $padding = ord($data[strlen($data) - 1]);
  return substr($data, 0, -$padding);
}
 
function aes256_cbc_decrypt($key, $data, $iv) {
  if(32 !== strlen($key)) $key = hash('SHA256', $key, true);
  if(16 !== strlen($iv)) $iv = hash('MD5', $iv, true);
  $data = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, base64_decode($data), MCRYPT_MODE_CBC, $iv);
  $padding = ord($data[strlen($data) - 1]);
  return substr($data, 0, -$padding);
}