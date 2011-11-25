<?php
 /**
 * Fix paypal 99 form items limit
 *
 * @category   Flagbit
 * @package    Flagbit_Paypal_Model_Api_Standard
 * @author     Flagbit GmbH & Co. KG <mike.becker@flagbit.de>
 */
class Flagbit_Paypal_Model_Api_Standard extends Mage_Paypal_Model_Api_Standard
{

    /**
     * Combine products by vat if amount greater this value
     * @var int
     */
    protected $_combineProductsLimit = 90;


    /**
     * Add shipping total as a line item.
     * For some reason PayPal ignores shipping total variables exactly when line items is enabled
     * Note that $i = 1
     *
     * @param array $request
     * @param int $i
     * @return true|null
     */
    protected function _exportLineItems(array &$request, $i = 1)
    {
        if (!$this->_cart) {
            return;
        }

        if ($this->getIsLineItemsEnabled()) {
            $this->_cart->isShippingAsItem(true);
        }
        
        # array with items
        #$_items = parent::_exportLineItems($request, $i);
        $_items = $this->_cart->getItems();

        if (empty($_items) || !$this->getIsLineItemsEnabled()) {
            return;
        }
        
        if( count($_items) <= $this->_combineProductsLimit ) {
            # original output
            #return $_items;
            return parent::_exportLineItems($request, $i);
        }

        // always add cart totals, even if line items are not requested
        if ($this->_lineItemTotalExportMap) {
            foreach ($this->_cart->getTotals() as $key => $total) {
                if (isset($this->_lineItemTotalExportMap[$key])) { // !empty($total)
                    $privateKey = $this->_lineItemTotalExportMap[$key];
                    $request[$privateKey] = $this->_filterAmount($total);
                }
            }
        }

        # modified output
        $result = null;
        
        $_vatArr = array();
        
        $_singleItemCount = 1;

        # loop all cart items an build vat array
        foreach ($_items as $item) {

            $_itemTaxPercent = $item->getTaxPercent();
            
            # get qty to manually calculate the total price
            $_itemQty = $item->getQty();

            # vat_7, vat_19
            if( $_itemTaxPercent != '' ) {
                $_itemTaxKey = sprintf('vat_%s', $_itemTaxPercent);
            } else {
                # items with no tax. shipping for example
                $_itemTaxKey = sprintf('single_%d', $_singleItemCount++);
            }

            # init arrays
            if( !isset($_vatArr[$_itemTaxKey]) ) {
                $_vatArr[$_itemTaxKey] = array();
            }
            if( !isset($_vatArr[$_itemTaxKey]['amount']) ) {
                $_vatArr[$_itemTaxKey]['amount'] = 0;
            }
            
            # loop id, name, qty, amount and tax_percent
            foreach ($this->_lineItemExportItemsFormat as $publicKey => $privateFormat) 
            {
                $result = true;
                # $item->getName(), $item->getTaxPercent()
                $value = $item->getDataUsingMethod($publicKey);
                
                if (isset($this->_lineItemExportItemsFilters[$publicKey]))
                {
                    $callback   = $this->_lineItemExportItemsFilters[$publicKey];
                    $value         = call_user_func(array($this, $callback), $value);
                }
                if (is_float($value)) {
                    $value = $this->_filterAmount($value);
                }
                
                if( $_itemTaxPercent != '' && $publicKey != 'amount') {
                    continue;
                }

                # summarize only amount
                if( $publicKey == 'amount' ) {
                    # $_vatArr['vat_19][amount] += 5x100
                    $_vatArr[$_itemTaxKey][$publicKey] += ($_itemQty * $value);
                } else {
                    $_vatArr[$_itemTaxKey][$publicKey] = $value;
                }
            }
            
            if( !isset($_vatArr[$_itemTaxKey]['qty']) )
            {
                $_vatArr[$_itemTaxKey]['qty']  = 1;
                $_vatArr[$_itemTaxKey]['id']   = md5($_itemTaxKey.rand(1,100).date('Y-m-d H:i:s'));
                $_vatArr[$_itemTaxKey]['name'] = sprintf(Mage::helper('flagbit_paypal')->__('Products with %s percent vat'),$_itemTaxPercent);
            }
        }

        # build $request array to use in Mage_Paypal_Block_Standard_Redirect
        foreach ($_vatArr as $key => $value)
        {
            # loop id, name, qty, amount and tax_percent
            foreach ($this->_lineItemExportItemsFormat as $publicKey => $privateFormat) 
            {
                #                                         $_vatArr['vat_19][amount]
                $request[sprintf($privateFormat, $i)] = $value[$publicKey];
            }
            $i++;
        }

        return $result;
        
    }
    
}