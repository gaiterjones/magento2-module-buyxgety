<?php
/**
 *  Gaiterjones Plugin beforeupdateItems
 *
 *  @category    Gaiterjones
 *  @package     Gaiterjones_Buyxgety
 *  @author      modules@gaiterjones.com
 *
 */
namespace Gaiterjones\BuyXGetY\Plugin;
use Gaiterjones\BuyXGetY\Model\BuyXGetY;


class UpdateCartPlugin
{
    protected $_buyxgety;

    /**
     * Plugin constructor.
     */
    public function __construct(
        BuyXGetY $buyxgety
    ) {
        $this->_buyxgety = $buyxgety;

    }

    /**
     * @param \Magento\Checkout\Model\Cart $subject
     * @param $data
     * @return array
     */
    public function beforeupdateItems(\Magento\Checkout\Model\Cart $subject,$data)
    {
        // TO DO - REMOVE this
        //
        //$this->_buyxgety->log('beforeupdateItems Plugin');
        //$this->_buyxgety->CartUpdate();
        return [$data];

    }
}
