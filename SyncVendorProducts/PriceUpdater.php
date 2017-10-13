<?php
/**
 * Created by PhpStorm.
 * User: Максим
 * Date: 19.02.15
 * Time: 23:21
 */

class PriceUpdater {
    private $config;
    private $selectedVendors;
    private $excel;
    private $updateType;
    public function __construct($config)
    {
        $this->selectedVendors = $config['vendors'];
        /*$this->config = $config;*/$this->config = json_decode(file_get_contents(SYNC_VENDORS_CONFIG), true);
        $this->updateType = $config['master-data-type'];
        $inputFileType = 'Excel5';

        if ($this->updateType == 'excel')
        {
            /**  Create a new Reader of the type defined in $inputFileType  **/
            $objReader = PHPExcel_IOFactory::createReader($inputFileType);
            $objReader->setReadDataOnly(true);
            $this->excel = $objReader->load(DIR_PRICE_SHEETS_FOLDER . $this->config['master-file']['name']);
        }
    }

    public function updatePrices()
    {
		$updatePrices = db_get_field("SELECT value FROM cscart_settings WHERE option_name='update_prices_from_vendors' AND section_id='vendors'");
        if ($updatePrices == "Y") {
            $priceColumn = 'cscart_product_prices.price';
            $qtyColumn = 'amount';
        }
        else{
            $priceColumn = 'temp_price';
            $qtyColumn = 'temp_qty';
        }
        if ($this->updateType == 'excel')
        {
            $lastRow = $this->excel->getActiveSheet()->getHighestRow();
            for ($row = 2; $row <= $lastRow; $row++) {
                $itemId = $this->excel->getActiveSheet()->getCell('A'.$row)->getValue();
                $finalPrice = 0;
                $qty = 0;
                foreach($this->config['vendors'] as $vendor)
                {
                    $price = $this->excel->getActiveSheet()->getCell($vendor["master-file-price-column"].$row)->getValue();
                    if ($finalPrice < $price)
                        $finalPrice = $price;
                    $qty += $this->excel->getActiveSheet()->getCell($vendor["master-file-qty-column"].$row)->getValue();
                }
                db_query("UPDATE ?:products, vendor_items SET ?s= ?i * (vendor_items.interest/100 + 1), ?s = ?i WHERE product_code= ?s AND vendor_items.item_id= '" . $itemId . "'", $priceColumn, $finalPrice, $qtyColumn, $qty,  $itemId);
            }
        }
        elseif ($this->updateType == 'db')
        {
            $qty_columns = array();
            //Construct id, price and quantity column names
            foreach($this->config['vendors'] as $vendor)
            {
                $id_columns[] = $vendor['master-file-item-column-name'];
                $price_columns[] = $vendor['master-file-price-column-name'];
                $qty_columns[] = $vendor['master-file-qty-column-name'];
            }
            //for each selected vendor update item prices.
            foreach ($this->selectedVendors as $vendorName => $vendorData) {
                db_get_array(
                    "UPDATE cscart_products, cscart_product_prices, vendor_prices, vendor_items
                      SET cscart_products.list_price =IF(cscart_product_prices.price != 0 AND cscart_products.sale=0, cscart_product_prices.price, cscart_products.list_price),
                        " . $priceColumn . "=ROUND(GREATEST(0, " . implode(",", $price_columns) . ") * (vendor_items.interest/100 + 1), -1),
                         " . $qtyColumn . "=" . implode("+", $qty_columns) . "
                      WHERE product_code=vendor_prices.item_id
                            AND vendor_items.item_id=product_code
                            AND cscart_product_prices.product_id=cscart_products.product_id
                            AND cscart_products.sale = 0
                            AND vendor_prices." . $vendorName . "_price > 0"
                );
            }
			db_query(
                "UPDATE cscart_products as products
                    JOIN vendor_prices ON vendor_prices.item_id = products.product_code
                    SET products.amount = 0
                    WHERE vendor_prices.item_id IS NULL"
            );
            //update zero amounts
            db_query(
                "UPDATE cscart_products as products
                    JOIN vendor_prices ON vendor_prices.item_id = products.product_code
                    SET products.amount = 0
                    WHERE " . implode("+", $qty_columns) ."= 0
                    AND products.sale = 0"
            );
        }

    }

} 