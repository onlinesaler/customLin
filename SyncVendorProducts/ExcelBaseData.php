<?php
/**
 * Created by PhpStorm.
 * User: Максим
 * Date: 09.02.15
 * Time: 21:10
 */
include_once DIR_SYNC_VENDORS . "PHPExcel.php";
include_once DIR_SYNC_VENDORS . "SyncVendor.php";

class ExcelBaseData implements IBaseDataProvider{

    private $fileName;
    private $objReader;
    public $objPHPExcel;

    public function __construct($fileName)
    {
        $this->fileName = $fileName;

        /**  Create a new Reader of the type defined in $inputFileType  **/
		$inputFileType = PHPExcel_IOFactory::identify($fileName);
        $this->objReader = PHPExcel_IOFactory::createReader($inputFileType);
        $worksheetList = $this->objReader->listWorksheetNames($this->fileName);
        if ($fileName == "BASE_KZ.xlsm" || $fileName == "BASE_KZ_light.xlsm") {
            $worksheetList = array($worksheetList[2], $worksheetList[3]);
        }
        $this->objReader->setLoadSheetsOnly($worksheetList);
        $this->objReader->setReadDataOnly(true);
        SyncVendor::log("Begin loading " . $this->fileName . " base file.");
        $this->objPHPExcel = $this->objReader->load($this->fileName);
        SyncVendor::log("Base excel file loaded.");
    }

    //note - $column is an array with 2 members: base-data-column, base-data-column-name
    public function getItemIds($vendorItemId, $column)
    {
        $itemIds = array();
        $lastRow = $this->objPHPExcel->getActiveSheet(0)->getHighestRow();
        for ($row = 2; $row <= $lastRow; $row++) {
            $cell = $this->objPHPExcel->getActiveSheet(0)->getCell($column['base-data-column'].$row)->getValue();
            if ($cell == $vendorItemId)
            {
                $itemIds[] = $this->objPHPExcel->getActiveSheet(0)->getCell("A".$row)->getValue();
            }
        }
        return $itemIds;
    }

} 