<?php

class Flagbit_Paypal_Model_Cart extends Mage_Paypal_Model_Cart
{

    /**
     * Add a usual line item with amount and qty
     *
     * @param Varien_Object $salesItem
     * @return Varien_Object
     */
    protected function _addRegularItem(Varien_Object $salesItem)
    {
        if ($this->_salesEntity instanceof Mage_Sales_Model_Order) {
            $qty = (int) $salesItem->getQtyOrdered();
            $amount = (float) $salesItem->getBasePrice();
            // TODO: nominal item for order
        } else {
            $qty = (int) $salesItem->getTotalQty();
            $amount = $salesItem->isNominal() ? 0 : (float) $salesItem->getBaseCalculationPrice();
        }
        // workaround in case if item subtotal precision is not compatible with PayPal (.2)
        $subAggregatedLabel = '';
        if ($amount - round($amount, 2)) {
            $amount = $amount * $qty;
            $subAggregatedLabel = ' x' . $qty;
            $qty = 1;
        }

        // aggregate item price if item qty * price does not match row total
        if (($amount * $qty) != $salesItem->getBaseRowTotal()) {
            $amount = (float) $salesItem->getBaseRowTotal();
            $subAggregatedLabel = ' x' . $qty;
            $qty = 1;
        }

        return $this->addItem($salesItem->getName() . $subAggregatedLabel, $qty, $amount, $salesItem->getSku(), $salesItem->getTaxPercent());
    }


    /**
     * Add a line item
     *
     * @param string $name
     * @param numeric $qty
     * @param float $amount
     * @param string $identifier
     * @param string $taxpercent
     * @return Varien_Object
     */
    public function addItem($name, $qty, $amount, $identifier = null, $taxpercent = NULL)
    {
        $this->_shouldRender = true;
        $item = new Varien_Object(array(
            'name'   => $name,
            'qty'    => $qty,
            'amount' => (float)$amount,
            'tax_percent' => (string)$taxpercent,
        ));
        if ($identifier) {
            $item->setData('id', $identifier);
        }
        $this->_items[] = $item;
        return $item;
    }
    
}
