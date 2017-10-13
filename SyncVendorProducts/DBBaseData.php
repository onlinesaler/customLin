<?php
/**
 * Created by PhpStorm.
 * User: Максим
 * Date: 10.02.15
 * Time: 19:29
 */
include_once DIR_SYNC_VENDORS. "IBaseDataProvider.php";
include_once DIR_SYNC_VENDORS. "ExcelBaseData.php";

class DBBaseData implements IBaseDataProvider{

    private $config;
    private $excel;

    public function __construct($syncConfig)
    {
        $this->config = $syncConfig;
        if ($syncConfig['base-recreate-table'] == true)
        {
            $counter = 0;
            SyncVendor::log("Database table creation started.");
            $columns[] = 'item_id';
            $columns[] = 'interest';
            db_query("DROP TABLE IF EXISTS " . $syncConfig['base-table']);
            $query = "CREATE TABLE " . $syncConfig['base-table'] . " ( item_id VARCHAR(50) NOT NULL, interest DECIMAL(5,2) NOT NULL DEFAULT 100.00, " ;
            foreach ($syncConfig['vendors'] as $vendor)
            {
                $query .= $vendor['base-data-column-name'] . " VARCHAR(50) NOT NULL,";
                $columns[] = $vendor['base-data-column-name'];
            }
            $query = rtrim($query, ",");
            $query .= ") ENGINE=InnoDB   DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;";
            db_query($query);

            $excelBaseData = new ExcelBaseData(DIR_SYNC_VENDORS . $syncConfig['base-file']);
            $excel = $excelBaseData->objPHPExcel;
            $this->excel = $excel;
            $lastRow = $excel->setActiveSheetIndex(0)->getHighestRow();
            for ($row = 2; $row <= $lastRow; $row++) {
                $query = "INSERT INTO " . $syncConfig['base-table'] . " ";
                $values = array();
                $itemId = $excel->setActiveSheetIndex(0)->getCell("A".$row)->getValue();
                $values['item_id'] = "'" . $itemId . "'";
                $values['interest'] = $this->getInterest($itemId);
                foreach ($syncConfig['vendors'] as $vendor)
                {
                    $values[] = "'" . $excel->setActiveSheetIndex(0)->getCell($vendor["base-data-column"].$row)->getValue() . "'";
                    //$values[] = $value ? $value : "''";
                }
                $query .= "(" . implode(",", $columns) . ") VALUES (" . implode(",", $values) . ")";
                db_query($query);
                $counter++;
                if (fmod($counter, 1000) == 0)
                {
                    SyncVendor::log($counter . " records inserted.");
                }
            }
            SyncVendor::log("Database table creation ended. " . $counter . " records inserted.");
            $excel->disconnectWorksheets();
            unset($this->objPHPExcel);
			
        } elseif ($syncConfig['base-update-from-excel'] == true) // Update base item data from excel without deleting old data
        {
            self::importVendorItems(DIR_SYNC_VENDORS . $this->config['base-file']);
        }
    }

    public function getItemIds($vendorItemId, $column)
    {
		$vendorItemId = addslashes($vendorItemId);
        $query = "SELECT item_id FROM " .  $this->config['base-table'] . " WHERE " .
            $column['base-data-column-name'] . " LIKE '" . $vendorItemId . "' OR " .
            $column['base-data-column-name'] . " LIKE '" . $vendorItemId . ",%' OR " .
            $column['base-data-column-name'] . " LIKE '%," . $vendorItemId . ",%' OR " .
            $column['base-data-column-name'] . " LIKE '%," . $vendorItemId . "'";
        return db_get_fields($query);
    }

    private function getInterest($itemId)
    {
        $defaultInterest = 100;
        $lastRow = $this->excel->setActiveSheetIndex(1)->getHighestRow();
        for ($row = 1; $row <= $lastRow; $row++) {
            $cell = $this->excel->setActiveSheetIndex(1)->getCell('A'.$row)->getValue();
            if ($cell == $itemId)
            {
                $interest = $this->excel->setActiveSheetIndex(1)->getCell("K". $row)->getValue();
                return $interest ? $interest : $defaultInterest;
            }
        }
        return $defaultInterest;
    }
	
    public function importVendorItems($file)
    {
        $counter = 0;
        $excelBaseData = new ExcelBaseData($file);
        $excel = $excelBaseData->objPHPExcel;
        $this->excel = $excel;
        $lastRow = $excel->setActiveSheetIndex(0)->getHighestRow();
        for ($row = 2; $row <= $lastRow; $row++) {
            $values = array();
            $columns = array();
            $itemId = $excel->setActiveSheetIndex(0)->getCell("A".$row)->getValue();
            $values["item_id"] = "'" . $itemId . "'";
            $columns[] = "item_id";
            foreach ($this->config['vendors'] as $vendorName => $vendor)
            {
                $value = $excel->setActiveSheetIndex(0)->getCell($vendor["base-data-column"].$row)->getValue();
                if ($value) {
					if ($vendorName == 'interest'){
                        $vendor['base-data-column-name'] = 'interest';
                    }
                    $columns[] =  $vendor['base-data-column-name'];
                    $values[$vendor['base-data-column-name']] = "'" . $value . "'";
                }
            }
            if (count($columns)>1) {
                $query = "INSERT INTO " . $this->config['base-table'] . " ";
                $query .= "(" . implode(",", $columns) . ") VALUES (" . implode(",", $values) . ")";
                $query .= " ON DUPLICATE KEY UPDATE ";
                $updateColumns = array();
                foreach ($values as $column => $value) {
                    $updateColumns[] .= $column . "=" . $value;
                }
                $query .= implode(",", $updateColumns);
                db_query($query);
                $counter++;
            }
        }
        return $counter;
    }

    public function setOneVendor($vendor_id, $column)
    {
        $this->config['vendors'][$vendor_id]['base-data-column'] = $column;
        $this->config['vendors'] = array($vendor_id => $this->config['vendors'][$vendor_id]);
    }

}