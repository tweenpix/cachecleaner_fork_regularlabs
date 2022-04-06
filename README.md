# cachecleaner_fork_regularlabs
Модификация плагина для Joomla 	System - Regular Labs - Cache Cleaner
https://regularlabs.com/cachecleaner/

Дописана часть функционала, которая позволяет очищать fastcgi_cache nginx для joomla

Модификация затрагивает файл Cache.php
```php
	public static function purge(){
  //....
  	//apexweb purge fastcgi_cache
		self::Delete('/var/cache/nginx/fastcgi_cache/');//set dir with path Задаем директорию хранения кэша fastcgi nginx
		///////////////////////
  //....
  return self::getResult();
  }
  
  //apexweb fastcgi_cache purger
	//function from https://stackoverflow.com/a/1360458
	//https://apexweb.ru
	public static function Delete($path)
		{
			if (is_dir($path) === true)
			{
				$files = array_diff(scandir($path), array('.', '..'));

				foreach ($files as $file)
				{
						self::Delete(realpath($path) . '/' . $file);
						error_log('file '.$path.'/'.$file.' is deleted ');
				}

				return rmdir($path);
				error_log('dir '.$path.' is deleted ');

			}

			else if (is_file($path) === true)
			{
				return unlink($path);
				error_log('dir '.$path.' is deleted ');
			}

			return false;
		}
//////////////
```
При очистке кэша в Joomla, автоматически будет очищаться кэш fastcgi nginx
