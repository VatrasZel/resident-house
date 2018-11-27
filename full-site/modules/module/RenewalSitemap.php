<?php
	/**
	 *  @brief (Перевіряє наявніть нових сторінок новин та додає їх в sitemap.xml)
	 *  
	 *  @details (Функція виконується в адмінці!)
	 */
function renewalSitemap()
{  		
		GLOBAL $len_default, $site_url,$news_url_in_db,$data_xml,$data_xml_news, $db;
		
		if (empty($db))
		{
			GLOBAL $DB;
			$db=$DB;
		}

		function setPref($pref)
		{
			GLOBAL $len_default;
			if ($len_default==$pref) 
			{
				$lang='';
			}
			else
			{
				$lang='/'.$pref;
			}
			return $lang;
		}

		function addPageInXml ($url)
		{
			GLOBAL $news_url_in_db,$data_xml,$data_xml_news;
					
			$news_url_in_db[]=$url;
					
			if (array_search($url,$data_xml_news)===false)
			{
						
				$data_xml[]=(object) array('loc' => $url,
													'lastmod' => date(DATE_RFC3339),
													'changefreq' => 'daily',
													'priority' => "1.00"
													);
			}
		}

		$file = $_SERVER['DOCUMENT_ROOT'].'sitemap.xml';
		$xml = file_get_contents($file);
		$movies = new \SimpleXMLElement($xml, LIBXML_NOCDATA);

		$data_xml_news=[];

		foreach($movies->url as $key=> $item)
		{

			if (strlen($item->loc)==strlen($site_url.'/') || strlen($item->loc)==strlen($site_url.'/')+3)
			{
				$item->changefreq='always';
			}	
			else{
				$item->changefreq='daily';
			}
		
			$data_xml[]=$item;
			end($data_xml);	
			
			if (strripos($item->loc, $site_url.setPref('ua').'/section')!==false)
			{
				unset($data_xml[key($data_xml)]);
			}
			if (strripos($item->loc, $site_url.setPref('ru').'/section')!==false)
			{
				unset($data_xml[key($data_xml)]);
			}			
			if (strripos($item->loc, $site_url.setPref('en').'/section')!==false)
			{
				unset($data_xml[key($data_xml)]);
			}	

			if (strripos($item->loc, '/news/')!==false &&  substr($item->loc, -6)!='/news/')
			{
				$data_xml_news[key($data_xml)] = $item->loc;
			}
			
		}

		$data_xml_=$data_xml;

			/*Видаляєм із списку sitemap сторінки новин, яких вже нема
			*/
			$result = $db->prepare("SELECT news_code, title_ua, title_ru,title_en, isActive FROM `news` ");
			$result->execute();    
			$result->bind_result($s['news_code'],$s['title_ua'],$s['title_ru'],$s['title_en'],$s['isActive']);	

			while($result->fetch())
			{
				if ($s['isActive']!=0)
				{
					continue;
				}
				if ($s['title_ua'])
				{							
					addPageInXml($site_url.setPref('ua').'/news/'.$s['news_code'].'/');				
				}
				if ($s['title_ru'])
				{			
					addPageInXml($site_url.setPref('ru').'/news/'.$s['news_code'].'/');
				}		
				if ($s['title_en'])
				{			
					addPageInXml($site_url.setPref('en').'/news/'.$s['news_code'].'/');
				}			
			}	


			foreach($data_xml_news as $key=> $t)
			{
				if (array_search($t,$news_url_in_db)===false)
				{
					unset($data_xml[$key]);
				}
			}		
			/**
				*/
		
		/** Генерування sitemap при виявленні змін сторінок
		*/
		end($data_xml);
		end($data_xml_);
		
		if (count($data_xml)!=count($data_xml_) || key($data_xml)!=key($data_xml_))
		{
							
			require_once(dirname(__FILE__)."/../../sitemap/common.inc.php");

			set_time_limit(0);
			ini_set('memory_limit', '64M');

			$dir = $_SERVER['DOCUMENT_ROOT'];//document root path
				
			$tmp_dir = dirname(__FILE__);//temp path
			$base_url =  $site_url;//url with sitemaps (http://mysite.ru/sitemap.xml)

			$config = array('path' => $dir , 'tmp_dir'=>$tmp_dir,'base_url'=>$base_url,'gzip'=>false, 'gzip_level'=>9);

			$builder = new SitemapBuilder($config);
				
			$builder->start();
			$time = time();
			
			foreach ($data_xml as $t)
			{
				$builder->addUrl(array('loc'=>$t->loc,'lastmod'=>$time,'priority'=>$t->priority,'changefreq'=>$t->changefreq));
			}
	 
			$builder->commit();
		}
	}	
