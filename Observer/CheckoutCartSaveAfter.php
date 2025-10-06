<?php
/**
 *  Gaiterjones Observer CheckoutCartSaveAfter
 *
 *  @category    Gaiterjones
 *  @package     Gaiterjones_Buyxgety
 *  @author      modules@gaiterjones.com
 */

namespace Gaiterjones\BuyXGetY\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Registry;
use Gaiterjones\BuyXGetY\Model\BuyXGetY;
use Gaiterjones\BuyXGetY\Model\SpendXGetY;

class CheckoutCartSaveAfter implements ObserverInterface
{
    /** @var BuyXGetY */
    protected $_buyxgety;

    /** @var SpendXGetY */
    protected $_spendxgety;

    /** @var Registry */
    protected $registry;

    // registry key used to prevent re-entrancy
    private const FLAG_KEY = 'gaiterjones_buyxgety_processing';

    public function __construct(
        BuyXGetY  $buyxgety,
        SpendXGetY $spendxgety,
        Registry   $registry
    ) {
        $this->_buyxgety   = $buyxgety;
        $this->_spendxgety = $spendxgety;
        $this->registry    = $registry;
    }

    public function execute(Observer $observer)
    {
        // Prevent recursion when cart->save()/collectTotals triggers this event again
        if ($this->registry->registry(self::FLAG_KEY)) {
            return;
        }

        try {
            $this->registry->register(self::FLAG_KEY, true);

            // $this->_buyxgety->log('BUYXGETY / checkout_cart_save_after Observer');
            $this->_buyxgety->CartUpdate();
            $this->_spendxgety->CartUpdate();

        } finally {
            // Always clear the guard, even if an exception occurs
            $this->registry->unregister(self::FLAG_KEY);
        }
    }
}
