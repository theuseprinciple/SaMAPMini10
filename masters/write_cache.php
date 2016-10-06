<?php
  defined('_cachaccess') or die('Restricted access');
  $handle = fopen($cache_file, 'w'); // Открываем файл для записи и стираем его содержимое
  fwrite($handle, ob_get_contents()); // Сохраняем всё содержимое буфера в файл
  fwrite($handle, "<!-- cached: ".date("Y-m-d H:i:s")."-->");
  fclose($handle); // Закрываем файл
  ob_end_flush(); // Выводим страницу в браузере
?>