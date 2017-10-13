<?php
/**
 * Created by PhpStorm.
 * User: Максим
 * Date: 22.02.15
 * Time: 18:28
 */

class DBMasterData implements IMasterData{

    private $config;
    private $baseData;
    private $clearMissingData;

    function __construct($config)
    {
        $this->config = $config;
        $this->clearMissingData = db_get_field("SELECT value FROM cscart_settings WHERE option_name='clear_missing_vendor_data' AND section_id='vendors'");

		/*db_query(addslashes("DROP TABLE IF EXISTS " . $this->config['master-table']));
		$query = "CREATE TABLE " . $this->config['master-table'] . " ( item_id VARCHAR(50) NOT NULL PRIMARY KEY, " ;
		foreach ($this->config['vendors'] as $vendor)
		{
			$query .= $vendor['master-file-item-column-name'] . " VARCHAR(50) NOT NULL,";
			$query .= $vendor['master-file-price-column-name'] . " DECIMAL(12,2) NOT NULL DEFAULT 0.00,";
			$query .= $vendor['master-file-qty-column-name'] . " MEDIUMINT(8) NOT NULL DEFAULT 0,";

		}
		$query = rtrim($query, ",");
		$query .= ") ENGINE=InnoDB   DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;";
		db_query($query);*/
        
        /*$query = 'ALTER TABLE ' . $this->config['master-table'] . ' ';
        foreach ($this->config['vendors'] as $vendor)
        {
            $query .= " DROP COLUMN " . $vendor['master-file-item-column-name'] . ', ';
            $query .= " DROP COLUMN " . $vendor['master-file-price-column-name'] . ', ';
            $query .= " DROP COLUMN " . $vendor['master-file-qty-column-name'];
        }  
        db_query($query);  */

        $executeQuery = false;
        $columns = db_get_array("SELECT * FROM " . $this->config['master-table'] . " LIMIT 1");
        $columns = $columns[0];
        $query = "UPDATE " . $this->config['master-table'] . ' SET ';
        foreach ($this->config['vendors'] as $vendor)
        {
            if (isset($columns[$vendor['master-file-item-column-name']])){
                $query .= " " . $vendor['master-file-item-column-name'] . " = '',";
                $query .= " " . $vendor['master-file-price-column-name'] . "  = 0,";
                $query .= " " . $vendor['master-file-qty-column-name'] . " = 0,";
                $executeQuery = true;
            }
            else
            {
                $newVendors[] = array(  'master-file-item-column-name' => $vendor['master-file-item-column-name'],
                                        'master-file-price-column-name' => $vendor['master-file-price-column-name'],
                                        'master-file-qty-column-name' => $vendor['master-file-qty-column-name']);
            }

        }
        if($executeQuery) {
            db_query(rtrim($query, ","));
        }

        if(!empty($newVendors)) {
            $query = 'ALTER TABLE ' . $this->config['master-table'] . ' ';
            foreach ($newVendors as $vendor) {
                $query .= " ADD COLUMN " . $vendor['master-file-item-column-name'] . " VARCHAR(50) NOT NULL,";
                $query .= " ADD COLUMN " . $vendor['master-file-price-column-name'] . " DECIMAL(12,2) NOT NULL DEFAULT 0.00,";
                $query .= " ADD COLUMN " . $vendor['master-file-qty-column-name'] . " MEDIUMINT(8) NOT NULL DEFAULT 0,";

            }
            db_query(rtrim($query, ","));
        }


    }

    public function importData(Vendor $vendor)
    {
        $vendorConfig = $vendor->getConfig();
        if ($this->clearMissingData == "Y") {
            $clearQuery = "UPDATE vendor_prices SET " .
                $vendorConfig['master-file-price-column-name'] . " = 0, " .
                $vendorConfig['master-file-qty-column-name'] . " = 0";
            db_query($clearQuery);
        }
        while (!$vendor->endReached())
        {
            $vendorItemData = $vendor->getNextLine();
            if (isset($vendorItemData['item']) && $vendorItemData['item'] != "" && $vendorItemData['price'] > 0)
            {
                $itemIds = $this->baseData->getItemIds($vendorItemData['item'], $vendor->getBaseDataColumn());
                if (!empty($itemIds)){
                    foreach($itemIds as $itemId)
                    {
                    if ($vendorItemData['price'] > 0) {						
    						$query = "INSERT INTO " . $this->config['master-table'] . " (" .
    							"item_id," .
    							$vendorConfig['master-file-item-column-name'] ."," .
    							$vendorConfig['master-file-price-column-name'] ."," .
    							$vendorConfig['master-file-qty-column-name'] . ") VALUES (" .
    							"'" . $itemId . "'," .
    							"'". $vendorItemData['item'] . "'," .
    							$vendorItemData['price'] . "," .
    							$vendorItemData['qty'] . ") ON DUPLICATE KEY UPDATE " .
    							$vendorConfig['master-file-item-column-name'] . "='" . $vendorItemData['item'] . "'," .
    							$vendorConfig['master-file-price-column-name'] . "= IF (" . $vendorConfig['master-file-price-column-name'] . "<" . $vendorItemData['price'] . "," . $vendorItemData['price'] . "," . $vendorConfig['master-file-price-column-name'] . ")," .
    							$vendorConfig['master-file-qty-column-name'] . "=" . $vendorConfig['master-file-qty-column-name'] ."+" . $vendorItemData['qty'];
    						db_query($query);		
                        }				
                    }
                }
            }
        }
    }

    public function finish()
    {
        // TODO: Implement finish() method.
    }

    public function setBaseDataProvider(IBaseDataProvider $baseDataProvider)
    {
        $this->baseData = $baseDataProvider;
    }
}