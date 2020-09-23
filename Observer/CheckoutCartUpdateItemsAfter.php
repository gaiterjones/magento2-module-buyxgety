<?php
/**
 *  Gaiterjones Observer CheckoutCartUpdateItemsAfter
 *
 *  @category    Gaiterjones
 *  @package     Gaiterjones_Buyxgety
 *  @author      modules@gaiterjones.com
 *
 */
    namespace Gaiterjones\BuyXGetY\Observer;

    use Magento\Framework\Event\ObserverInterface;
    use Gaiterjones\BuyXGetY\Model\BuyXGetY;

    class CheckoutCartUpdateItemsAfter implements ObserverInterface
    {

        protected $_buyxgety;

        public function __construct(
            BuyXGetY $buyxgety
        ) {
            $this->_buyxgety = $buyxgety;
        }

        public function execute(\Magento\Framework\Event\Observer $observer) {

            $this->_buyxgety->log('BUYXGETY / checkout_cart_update_items_after Observer');
            //$this->_buyxgety->CartUpdate();

        }

    }
