<?php
/**
 *  Gaiterjones Observer CheckoutCartProductAddAfter
 *
 *  @category    Gaiterjones
 *  @package     Gaiterjones_Buyxgety
 *  @author      modules@gaiterjones.com
 *
 */
    namespace Gaiterjones\BuyXGetY\Observer;

    use Magento\Framework\Event\ObserverInterface;
    use Gaiterjones\BuyXGetY\Model\BuyXGetY;
    use Gaiterjones\BuyXGetY\Model\SpendXGetY;

    class CheckoutCartProductAddAfter implements ObserverInterface
    {

        protected $_buyxgety;
        protected $_spendxgety;

        public function __construct(
            BuyXGetY $buyxgety,
            SpendXGetY $spendxgety
        ) {
            $this->_buyxgety = $buyxgety;
            $this->_spendxgety = $spendxgety;
        }

        public function execute(\Magento\Framework\Event\Observer $observer) {

            $this->_buyxgety->log('BUYXGETY / checkout_cart_product_add_after Observer');
            //$this->_buyxgety->CartUpdate();
            //$this->_spendxgety->CartUpdate();

        }

    }
