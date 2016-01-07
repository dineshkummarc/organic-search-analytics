<?php
	/**
	 *  PHP class for importing CSV files from Google Webmaster Tools.
	 *
	 *  Copyright 2015 PromInc Productions. All Rights Reserved.
	 *  
	 *  @author: Brian Prom <prombrian@gmail.com>
	 *  @link:   http://promincproductions.com/blog/brian/
	 *
	 */

	class WMTimport
	{


		/**
		 *  Break apart the filename to return the data pieces
		 *
		 *  @param $fileName     String   Filename of CSV file
		 */
		public function getDataFromReportName($fileName)
		{
			$fileNameParts = explode( "-", $_GET['file'] );
			$return = array();

			$return['report'] = strtolower( $fileNameParts[0] );
			$return['searchEngine'] = $fileNameParts[1];
			$return['domain'] = $fileNameParts[2];
			$return['dateStart'] = $fileNameParts[3];

			if( strpos( $fileNameParts[3], "_to_" ) ) {
				$dates = explode( "_to_", $fileNameParts[3] );
				$return['dateStart'] = $dates[0];
				$return['dateEnd'] =  $dates[1];
			} else {
				$return['dateStart'] = $return['dateEnd'] = $fileNameParts[3];
			}

			if( strpos( $fileNameParts[4], ".csv" ) ) {
				$return['type'] = substr( $fileNameParts[4], 0, strpos( $fileNameParts[4], ".csv" ) );
			} else {
				$return['type'] = $fileNameParts[4];
			}

			return $return;

		}


		/**
		 *  Import array of Google Search Analytics to database
		 *
		 *  @param $domain     String   Domain name for record
		 *  @param $date     String   Date for record YYYY-MM-DD
		 *  @param $searchType     String   Search type for record (web, image, video)
		 *  @param $searchAnalytics     Object   Search Analytics Results
		 *
		 *  @returns   Int   Count of records imported
		 */
		public function importGoogleSearchAnalytics($domain, $date, $searchType, $searchAnalytics) {
			echo "<h3>Import data for search type: ".$searchType."</h3>";
			$countImport = 0;
			foreach( $searchAnalytics->rows as $recordKey => $recordData ) {
				/* Prep data */
				$domain = addslashes( $domain );
				$searchType = addslashes( $searchType );
				$deviceType = addslashes( strtolower( $recordData['keys'][1] ) );
				$query = addslashes( $recordData['keys'][0] );

				$import = "INSERT into ".MySQL::DB_TABLE_SEARCH_ANALYTICS."(domain, date, search_engine, search_type, device_type, query, impressions, clicks, ctr, avg_position) values('$domain', '$date', 'google', '$searchType', '$deviceType', '{$query}','{$recordData['impressions']}','{$recordData['clicks']}','{$recordData['ctr']}','{$recordData['position']}')";

				echo "Record #".$countImport."<br>";
				echo $import."<br>";
				$result = $GLOBALS['db']->query($import);
				var_dump($result);
				echo "<br>";

				if( $result ) {
					$countImport++;
				} else {
					echo "<pre>";
					var_dump($result);
					echo "</pre>";
				}
				
				echo "----------<br>";

			}
			echo "<h3>Total records imported: ".$countImport."</h3>";
			return $countImport;
		}


		/**
		 *  Import array of Bing Search Keywords to database
		 *
		 *  @param $domain     String   Domain name for record
		 *  @param $searchKeywords     Object   Search Keywords Results
		 *
		 *  @returns   Int   Count of records imported
		 */
		public function importBingSearchKeywords($domain, $searchKeywords) {
			$searchKeywords = json_decode($searchKeywords);
			$countImport = 0;
		
			/* Check for prior import in DB */
			$lastImported = "SELECT MAX(date) AS 'lastImported' FROM ".MySQL::DB_TABLE_SEARCH_ANALYTICS." WHERE domain = '".$domain."' AND search_engine = 'bing'";
			if( $lastImportedResult = $GLOBALS['db']->query($lastImported) ) {
				$lastImportedDate = $lastImportedResult->fetch_row()[0];

				foreach( array_reverse( $searchKeywords->d ) as $recordKey => $recordData ) {
					preg_match( '/\d+/', $recordData->Date, $dateUnixMatch );
					$ctr = $recordData->Clicks / $recordData->Impressions;
					$date = date( "Y-m-d", substr($dateUnixMatch[0], 0, strlen($dateUnixMatch[0])-3) );
					$query = addslashes( $recordData->Query );
					$domain = addslashes( $domain );

					if( $date > $lastImportedDate ) {
						$import = "INSERT into ".MySQL::DB_TABLE_SEARCH_ANALYTICS."(domain, date, search_engine, query, impressions, clicks, ctr, avg_position, avg_position_click) values('$domain', '$date', 'bing', '{$query}','{$recordData->Impressions}','{$recordData->Clicks}','{$ctr}','{$recordData->AvgImpressionPosition}', '{$recordData->AvgClickPosition}')";

						if( $GLOBALS['db']->query($import) ) {
							$countImport++;
						}
					}
				}
			}
			return $countImport;
		}


	}
?>