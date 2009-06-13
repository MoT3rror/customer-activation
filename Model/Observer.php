<?php

/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @category   Mage
 * @package    Netzarbeiter_CustomerActivation
 * @copyright  Copyright (c) 2008 Vinai Kopp http://netzarbeiter.com/
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Netzarbeiter_CustomerActivation_Model_Observer extends Mage_Core_Model_Abstract
{
	/**
	 * Some random number ;)
	 */
	const EXCEPTION_CUSTOMER_NOT_ACTIVATED = 996;

	const XML_PATH_MODULE_DISABLED = 'customer/customeractivation/disable_ext';

	/**
	 * Fired on customer_login event
	 * Check if the customer has been activated (via adminhtml)
	 * If not, through login error
	 *
	 * @param Varien_Event_Observer $observer
	 */
	public function customerActivationLoginEvent($observer)
	{
		// event: customer_login
		if (Mage::getStoreConfig(self::XML_PATH_MODULE_DISABLED , Mage::app()->getStore())) return;
		
		$customer = $observer->getEvent()->getCustomer();
		$session = Mage::getSingleton('customer/session');
		
		if (! $customer->getData('customer_activated')) {
			
			$session->logout();
			
			if (Mage::app()->getRequest()->getActionName() == 'createpost') {
				$session->addSuccess(Mage::helper('customer')->__('Please wait for your account to be activated'));
			} else {
				//Mage::log("throwing exception");
				throw new Exception(
					Mage::helper('customer')->__('This account is not activated.'),
					self::EXCEPTION_CUSTOMER_NOT_ACTIVATED
				);
			}
		}
    }

	/**
	 * Flag new accounts as such
	 *
	 * @param Varien_Event_Observer $observer
	 */
	public function customerSaveBefore($observer)
	{
		$customer = $observer->getEvent()->getCustomer();

		if (Mage::getStoreConfig(self::XML_PATH_MODULE_DISABLED , Mage::app()->getStore($customer->getStoreId()))) return;

		if (! $customer->getId())
		{
			$customer->setCustomerActivationNewAccount(true);
		}
	}

	/**
	 * Send out emails
	 *
	 * @param Varien_Event_Observer $observer
	 */
	public function customerSaveAfter($observer)
	{
		$customer = $observer->getEvent()->getCustomer();
		
		if (Mage::getStoreConfig(self::XML_PATH_MODULE_DISABLED , Mage::app()->getStore($customer->getStoreId()))) return;

		try {

		if (Mage::app()->getStore()->isAdmin())
		{
			if (! $customer->getOrigData('customer_activated') && $customer->getCustomerActivated())
			{
				Mage::helper('customeractivation')->sendCustomerNotificationEmail($customer);
			}
		}
		else
		{
			if ($customer->getCustomerActivationNewAccount())
			{
				Mage::helper('customeractivation')->sendAdminNotificationEmail($customer);
			}
			$customer->setCustomerActivationNewAccount(false);
		}

		} catch (Exception $e) {
			//Mage::log($e->getMessage());
			Mage::throwException($e->getMessage());
		}
	}
}

