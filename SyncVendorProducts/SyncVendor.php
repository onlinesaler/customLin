<?php
/**
 * Created by PhpStorm.
 * User: Максим
 * Date: 04.02.15
 * Time: 17:24
 */

include_once("IBaseDataProvider.php");
include_once("IMasterData.php");
include_once("ExcelMasterData.php");
include_once("Vendor.php");
include_once("ExcelBaseData.php");
include_once("ExcelMasterData.php");
include_once("DBBaseData.php");
include_once("DBMasterData.php");
include_once("PriceUpdater.php");


class SyncVendor {
    private $config;
    private $masterData;
    private $baseData;

    public function __construct($config){
        fn_start_scroller();
        self::clearLog();
        self::log('Sync Vendor Prices started');
        $this->config = $config;
        $this->masterData = self::getMasterDataProvider($this->config);
        $this->baseData = self::getBaseDataProvider($this->config);
        $this->masterData->setBaseDataProvider($this->baseData);
        define("DIR_PRICE_SHEETS_FOLDER", DIR_SYNC_VENDORS . $this->config['price-sheets-folder']);
    }

    public function run(){
        foreach ($this->config['vendors'] as $vendorConfig)
        {
            $vendor = new Vendor($vendorConfig);
            $this->masterData->importData($vendor);
            //break;//only one vendor for now
        }
        $this->masterData->finish();
        self::log('Sync Vendor Prices ended.');
        self::log('Updating prices ...');
        $updater = new PriceUpdater($this->config);
        $updater->updatePrices();
        self::log('Finished updating prices.');
        fn_stop_scroller();
    }

    public static function getBaseDataProvider($config){
        switch ($config['base-data']){
            case 'excel':
                return new ExcelBaseData($config);
                break;
            case 'db':
                return new DBBaseData($config);
                break;
        }
    }
    public static function getMasterDataProvider($config){
        switch ($config['master-data-type']){
            case 'excel':
                return new ExcelMasterData(DIR_SYNC_VENDORS . $config['base-file']);
                break;
            case 'db':
                return new DBMasterData($config);
                break;
        }
    }

    public function downloadSheets(){
        self::log('Download started.');
        foreach($this->config['vendors'] as $vendorKey => $vendor){
            foreach ($vendor['price-sheets'] as $priceSheet)
            {
                if ($priceSheet['download'] == 'url')
                {
                    // Handle souzplastic vendor
                    if ($vendorKey == 'souzplastic')
                    {
                        $priceSheet['file-name'] = $this->adjustSheetNameForSouz($priceSheet['file-name'], 0);
                        $priceSheet['url'] = $this->adjustSheetNameForSouz($priceSheet['url'], 0);
                        for ($i=1; $i<=7; $i++)
                        {
                            $try = $this->downloadSheet($priceSheet['url'], $priceSheet['file-name']);
                            if (!$try)
                            {
                                $priceSheet['file-name'] = $this->adjustSheetNameForSouz($priceSheet['file-name'], $i);
                                $priceSheet['url'] = $this->adjustSheetNameForSouz($priceSheet['url'], $i);
                            }
                            else
                            {
                                $config = json_decode(file_get_contents(SYNC_VENDORS_CONFIG), true);
                                $config['vendors']['souzplastic']['price-sheets'][0]['file-name'] = $priceSheet['file-name'];
                                $config['vendors']['souzplastic']['price-sheets'][0]['url'] = $priceSheet['url'];
                                file_put_contents(SYNC_VENDORS_CONFIG, json_encode($config/*, JSON_PRETTY_PRINT*/));
                                break;                            }
                        }

                    }
                    elseif ($vendorKey == 'vialet')
                    {
                        $ch = curl_init();
                        $targetFile = fopen(DIR_PRICE_SHEETS_FOLDER .$priceSheet['file-name'], 'w' );
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_HEADER, 0);
                        curl_setopt($ch, CURLOPT_VERBOSE, 0);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/x-www-form-urlencoded"));
                        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (X11; Linux i686; rv:6.0) Gecko/20100101 Firefox/6.0Mozilla/4.0 (compatible;)");
                        curl_setopt($ch, CURLOPT_URL, $priceSheet['url']);
                        curl_setopt($ch, CURLOPT_FILE, $targetFile);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, "categories%5B%5D=6&categories%5B%5D=45&categories%5B%5D=48&categories%5B%5D=49&categories%5B%5D=51&categories%5B%5D=52&categories%5B%5D=598&categories%5B%5D=887&search=");
                        $result = curl_exec($ch);
                        fclose($targetFile);
                    }
                    // Handle all other normal vendors
                    else
                    {
                        $result = $this->downloadSheet($priceSheet['url'], $priceSheet['file-name']);
                        if (!isset($result))
                        {
                            if (is_readable(DIR_PRICE_SHEETS_FOLDER . $priceSheet['file-name']))
                            {
                                self::log(' Using old file.');
                            }
                            else
                            {
                                self::log(' File skipped.');
                            }
                        }
                    }
                }
                else if (($priceSheet['download'] == 'file') && !is_readable(DIR_PRICE_SHEETS_FOLDER . $priceSheet['file-name']))
                {
                    self::log('WARNING: File ' . $priceSheet['file-name'] . ' not found');
                }
                else
                {
                    self::log('Using file ' . $priceSheet['file-name'] . ' on server.');
                }
				$file = DIR_PRICE_SHEETS_FOLDER . $priceSheet['file-name'];
				// handle archieves
                if (pathinfo($file, PATHINFO_EXTENSION) == 'rar')
                {
                    self::log('Extracting file ' . $priceSheet['file-name'] . ' ...');						
					$command = 'rm -rf ' . DIR_PRICE_SHEETS_FOLDER . pathinfo($file, PATHINFO_FILENAME);
					exec($command);					
					$command = 'mkdir ' . DIR_PRICE_SHEETS_FOLDER . pathinfo($file, PATHINFO_FILENAME);
					exec($command);
					$command = 'rar e -sca ' . $file . " " . escapeshellarg(DIR_PRICE_SHEETS_FOLDER . pathinfo($file, PATHINFO_FILENAME));
					exec($command);
					sleep(1);					
					self::log('Files extracted.');
                }
            }
        }
        self::log('Download ended.');
    }

    private function adjustSheetNameForSouz($fileName, $offset)
    {
        $result = substr($fileName, 0, -14);
        $result .= date("Y-m-d", strtotime("-" . $offset . " days")) . ".xls";
        return $result;
    }

    private function downloadSheet($url, $file)
    {
        $download = fopen($url, 'r');
        if (!$download)
        {
            self::log('WARNING: Failed to download ' . $file . ' using ' . $url);
            return false;
        }
        else
        {
            $result = file_put_contents(DIR_PRICE_SHEETS_FOLDER . $file, $download);
            self::log('File ' . $file . ' downloaded (' . $result . ' bytes)');
            return $result;
        }
    }

    public static function log($message, $lineFeed = true)
    {

        $endLine = $lineFeed ? "\r\n" : "";
        $date = $lineFeed ? date("H:i:s") : "";
        fn_echo($message . "<br>");
        file_put_contents(DIR_SYNC_VENDORS . "syncVendor.log", $endLine .  $date . ":" . $message, FILE_APPEND);
    }

    private static function clearLog()
    {
        file_put_contents(DIR_SYNC_VENDORS . "syncVendor.log", "");
    }
}
