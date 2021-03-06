<?php
/**
** Parts of this code is written by Joomlaproffs.se Copyright (c) 2012, 2015 All Right Reserved.
** Many part of this code is from VirtueMart Team Copyright (c) 2004 - 2015. All rights reserved.
** Some parts might even be Joomla and is Copyright (C) 2005 - 2014 Open Source Matters, Inc. All rights reserved. 
** http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
** This source is free software. This version may have been modified pursuant
** to the GNU General Public License, and as distributed it includes or
** is derivative of works licensed under the GNU General Public License or
** other free or open source software licenses.
**
** THIS CODE AND INFORMATION ARE PROVIDED "AS IS" WITHOUT WARRANTY OF ANY 
** KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
** IMPLIED WARRANTIES OF MERCHANTABILITY AND/OR FITNESS FOR A
** PARTICULAR PURPOSE.

** <author>Joomlaproffs / Virtuemart team</author>
** <email>info@joomlaproffs.se</email>
** <date>2015</date>
*/
defined('_JEXEC') or die('Restricted access');

if(!class_exists('VmView'))require(VMPATH_SITE.DS.'helpers'.DS.'vmview.php');


class VirtueMartViewCart extends VmView {

	var $pointAddress = false;

 
	public function display($tpl = null) { 

		$app = JFactory::getApplication();
		$input = JFactory::getApplication()->input;

  
		$this->prepareContinueLink(); 
		if (VmConfig::get('use_as_catalog',0)) {
			vmInfo('This is a catalogue, you cannot access the cart');
			$app->redirect($this->continue_link);
		}
	
		$pathway = $app->getPathway();
		$document = JFactory::getDocument();
		$document->setMetaData('robots','NOINDEX, NOFOLLOW, NOARCHIVE, NOSNIPPET');
		
		$this->addTemplatePath(dirname(__FILE__).DS. 'tmpl'.DS);

		$layoutName = $this->getLayout();

		if (!$layoutName) $layoutName = vRequest::getCmd('layout', 'default');
		$this->assignRef('layoutName', $layoutName);
		$format = vRequest::getCmd('format');

		if (!class_exists('VirtueMartCart'))
		require(VMPATH_SITE . DS . 'helpers' . DS . 'cart.php');
		$cart = VirtueMartCart::getCart();

		$cart->prepareVendor();
		$this->cart = $cart;

		//Why is this here, when we have view.raw.php
		if ($format == 'raw') {
			vRequest::setVar('layout', 'mini_cart');
			$this->setLayout('mini_cart');
			$this->prepareContinueLink();
		}

		if ($layoutName == 'select_shipment') {

			$cart->prepareCartData();
			$this->lSelectShipment();

			$pathway->addItem(vmText::_('COM_VIRTUEMART_CART_OVERVIEW'), JRoute::_('index.php?option=com_virtuemart&view=cart', FALSE));
			$pathway->addItem(vmText::_('COM_VIRTUEMART_CART_SELECTSHIPMENT'));
			$document->setTitle(vmText::_('COM_VIRTUEMART_CART_SELECTSHIPMENT'));
		} else if ($layoutName == 'select_payment') {

			$cart->prepareCartData();

			$this->lSelectPayment();

			$pathway->addItem(vmText::_('COM_VIRTUEMART_CART_OVERVIEW'), JRoute::_('index.php?option=com_virtuemart&view=cart', FALSE));
			$pathway->addItem(vmText::_('COM_VIRTUEMART_CART_SELECTPAYMENT'));
			$document->setTitle(vmText::_('COM_VIRTUEMART_CART_SELECTPAYMENT'));
		} else if ($layoutName == 'order_done') {
			VmConfig::loadJLang( 'com_virtuemart_shoppers', true );
			$this->lOrderDone();

			$pathway->addItem( vmText::_( 'COM_VIRTUEMART_CART_THANKYOU' ) );
			$document->setTitle( vmText::_( 'COM_VIRTUEMART_CART_THANKYOU' ) );
		} else {
			VmConfig::loadJLang('com_virtuemart_shoppers', true);

			$this->renderCompleteAddressList();

			if (!class_exists ('VirtueMartModelUserfields')) {
				require(VMPATH_ADMIN . DS . 'models' . DS . 'userfields.php');
			}

			$userFieldsModel = VmModel::getModel ('userfields');

			$userFieldsCart = $userFieldsModel->getUserFields(
				'cart'
				, array('captcha' => true, 'delimiters' => true) // Ignore these types
				, array('delimiter_userinfo','user_is_vendor' ,'username','password', 'password2', 'agreed', 'address_type') // Skips
			);

			$this->userFieldsCart = $userFieldsModel->getUserFieldsFilled(
				$userFieldsCart
				,$cart->cartfields
			);

			if (!class_exists ('CurrencyDisplay'))
				require(VMPATH_ADMIN . DS . 'helpers' . DS . 'currencydisplay.php');

			$this->currencyDisplay = CurrencyDisplay::getInstance($cart->pricesCurrency);
			$currency	= $this->currencyDisplay;

			$customfieldsModel = VmModel::getModel ('Customfields');
			$this->assignRef('customfieldsModel',$customfieldsModel);

			$this->lSelectCoupon();

			$totalInPaymentCurrency = $this->getTotalInPaymentCurrency();

			$checkoutAdvertise =$this->getCheckoutAdvertise();
			
		

			if ($cart->getDataValidated()) {
				if($cart->_inConfirm){
					$pathway->addItem(vmText::_('COM_VIRTUEMART_CANCEL_CONFIRM_MNU'));
					$document->setTitle(vmText::_('COM_VIRTUEMART_CANCEL_CONFIRM_MNU'));
					$text = vmText::_('COM_VIRTUEMART_CANCEL_CONFIRM');
					$this->checkout_task = 'cancel';
				} else {
					$pathway->addItem(vmText::_('COM_VIRTUEMART_ORDER_CONFIRM_MNU'));
					$document->setTitle(vmText::_('COM_VIRTUEMART_ORDER_CONFIRM_MNU'));
					$text = vmText::_('COM_VIRTUEMART_ORDER_CONFIRM_MNU');
					$this->checkout_task = 'confirm';
				}
			} else {
				$pathway->addItem(vmText::_('COM_VIRTUEMART_CART_OVERVIEW'));
				$document->setTitle(vmText::_('COM_VIRTUEMART_CART_OVERVIEW'));
				$text = vmText::_('COM_VIRTUEMART_CHECKOUT_TITLE');
				$this->checkout_task = 'checkout';
			}
			$this->checkout_link_html = '<button type="submit"  id="checkoutFormSubmit" name="'.$this->checkout_task.'" value="1" class="vm-button-correct" ><span>' . $text . '</span> </button>';

            if (!class_exists('vmPSPlugin')) require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
				JPluginHelper::importPlugin('vmshipment');
				JPluginHelper::importPlugin('vmpayment');
				vmdebug('cart view oncheckout_opc ');
				if(!$this->lSelectShipment() or !$this->lSelectPayment()){
					vmInfo('COM_VIRTUEMART_CART_ENTER_ADDRESS_FIRST');
					$this->pointAddress = true;
				}

         

			if (VmConfig::get('oncheckout_opc', 1)) {
		
				
			} else {
				$this->checkPaymentMethodsConfigured();
				$this->checkShipmentMethodsConfigured();
			}

			if ($cart->virtuemart_shipmentmethod_id) {
				$shippingText =  vmText::_('COM_VIRTUEMART_CART_CHANGE_SHIPPING');
			} else {
				$shippingText = vmText::_('COM_VIRTUEMART_CART_EDIT_SHIPPING');
			}
			$this->assignRef('select_shipment_text', $shippingText);

			if ($cart->virtuemart_paymentmethod_id) {
				$paymentText = vmText::_('COM_VIRTUEMART_CART_CHANGE_PAYMENT');
			} else {
				$paymentText = vmText::_('COM_VIRTUEMART_CART_EDIT_PAYMENT');
			}
			$this->assignRef('select_payment_text', $paymentText);

			$cart->prepareAddressFieldsInCart();

			$layoutName = $cart->layout;
			//set order language
			$lang = JFactory::getLanguage();
			$order_language = $lang->getTag();
			$this->assignRef('order_language',$order_language);
		}

		$this->useSSL = VmConfig::get('useSSL', 0);
		$this->useXHTML = false;

		$this->assignRef('totalInPaymentCurrency', $totalInPaymentCurrency);
		$this->assignRef('checkoutAdvertise', $checkoutAdvertise);

		if(!class_exists('VmTemplate')) require(VMPATH_SITE.DS.'helpers'.DS.'vmtemplate.php');
		VmTemplate::setVmTemplate($this, 0, 0, $layoutName);

		//We never want that the cart is indexed
		$document->setMetaData('robots','NOINDEX, NOFOLLOW, NOARCHIVE, NOSNIPPET');

		if ($cart->_inConfirm) vmInfo('COM_VIRTUEMART_IN_CONFIRM');
		if ($cart->layoutPath) {
			$this->addTemplatePath($cart->layoutPath);
		}
		
		$_POST["agreed"] = $_POST["tos"];

		$current = JFactory::getUser();
		$this->allowChangeShopper = false;
		$this->adminID = false;
		if(VmConfig::get ('oncheckout_change_shopper')){
			if($current->authorise('core.admin', 'com_virtuemart') or $current->authorise('vm.user', 'com_virtuemart')){
				$this->allowChangeShopper = true;
			} else {
				$this->adminID = JFactory::getSession()->get('vmAdminID',false);
				if($this->adminID){
					if(!class_exists('vmCrypt'))
						require(VMPATH_ADMIN.DS.'helpers'.DS.'vmcrypt.php');
					$this->adminID = vmCrypt::decrypt($this->adminID);
					$adminIdUser = JFactory::getUser($this->adminID);
					if($adminIdUser->authorise('core.admin', 'com_virtuemart') or $adminIdUser->authorise('vm.user', 'com_virtuemart')){
						$this->allowChangeShopper = true;
					}
				}
			}
		}
		if($this->allowChangeShopper){
			$this->userList = $this->getUserList();
		}
		
		$task =  $input->getString("vmtask");
		
		if (file_exists(dirname(__FILE__)."/".$task.".php")) 
			require_once dirname(__FILE__)."/".$task.".php";
		
		if($task == "completecheckout")
		{
		     require_once dirname(__FILE__)."/updatecartaddress.php";
			 $checkout = $cart->checkoutData(false);
		     if($checkout)
			 {
				 echo "success";
			 }
			 else
			 {
			    echo "error";
			 } 
			 exit;
		}
		
		  if($task == "ajaxshipment")
		  {
			$this->lSelectShipment();
			echo json_encode($this->shipments_shipment_rates);
			exit;
		  }
		  if($task == "ajaxpayment")
		  {
			$this->lSelectPayment();
			echo json_encode($this->paymentplugins_payments);
			exit;
		  }
	
	  parent::display($tpl);
	}

	private function lSelectCoupon() {

		$this->couponCode = (!empty($cart->couponCode) ? $cart->couponCode : '');
		$this->coupon_text = $cart->couponCode ? vmText::_('COM_VIRTUEMART_COUPON_CODE_CHANGE') : vmText::_('COM_VIRTUEMART_COUPON_CODE_ENTER');
	}
	
	/**
	* lSelectShipment
	* find al shipment rates available for this cart
	*
	* @author Valerie Isaksen
	*/


	private function lSelectShipment() {
	  
	  $cart = $this->cart;
	  $cart->prepareAddressFieldsInCart();
	  if(empty($cart->BT['virtuemart_country_id']))
		{
		  if(count($cart->BTaddress['fields']))
		  {
		  	if (!empty($cart->BTaddress['fields']["virtuemart_country_id"]["virtuemart_country_id"])) {
				 $post['virtuemart_country_id'] = $cart->BTaddress['fields']["virtuemart_country_id"]["virtuemart_country_id"];
				 $cart->saveAddressInCart($post,'BT');
			}
			}
		}
	
		$found_shipment_method=false;
		$shipment_not_found_text = vmText::_('COM_VIRTUEMART_CART_NO_SHIPPING_METHOD_PUBLIC');
		$this->assignRef('shipment_not_found_text', $shipment_not_found_text);
		$this->assignRef('found_shipment_method', $found_shipment_method);
		
		$shipmentModel = VmModel::getModel('Shipmentmethod');
		$shipments = $shipmentModel->getShipments();

		$shipments_shipment_rates=array();
		if (!$this->checkShipmentMethodsConfigured()) {
			$this->assignRef('shipments_shipment_rates',$shipments_shipment_rates);
			return;
		}

		$selectedShipment = (empty($cart->virtuemart_shipmentmethod_id) ? 0 : $cart->virtuemart_shipmentmethod_id);
		
		if($cart->virtuemart_shipmentmethod_id == 0)
		{
		   if(vmconfig::get("set_automatic_shipment") > 0)
		   {
			  $cart->virtuemart_shipmentmethod_id = vmconfig::get("set_automatic_shipment");
		   }
		   else if(!empty($shipments[0]->virtuemart_shipmentmethod_id))
		   {
		      $cart->virtuemart_shipmentmethod_id = $shipments[0]->virtuemart_shipmentmethod_id;
		   }
		   
		}
		

		$shipments_shipment_rates = array();
		if (!class_exists('vmPSPlugin')) require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
		JPluginHelper::importPlugin('vmshipment');
		$dispatcher = JDispatcher::getInstance();

		$returnValues = $dispatcher->trigger('plgVmDisplayListFEShipment', array( $cart, $selectedShipment, &$shipments_shipment_rates));
		// if no shipment rate defined
		$found_shipment_method =count($shipments_shipment_rates);

		$ok = true;
		if ($found_shipment_method == 0)  {
			$validUserDataBT = $cart->validateUserData();

			if ($validUserDataBT===-1) {
				
					vmdebug('lSelectShipment $found_shipment_method === 0 show error');
					$ok = false;

			}

		}
		$shipmentarray = array();
		foreach($shipments_shipment_rates as $shipments) {
			if(is_array($shipments)) {
				foreach($shipments as $shipment) {
					$shipmentarray[]=$shipment;
			   }
			} else {
				$shipmentarray[]=$shipments;
			}
		}

		$shipment_not_found_text = vmText::_('COM_VIRTUEMART_CART_NO_SHIPPING_METHOD_PUBLIC');
		$this->assignRef('shipment_not_found_text', $shipment_not_found_text);
		$this->assignRef('shipments_shipment_rates', $shipmentarray);
		$this->assignRef('found_shipment_method', $found_shipment_method);

		return $ok;
	}
	
	/*
	 * lSelectPayment
	* find al payment available for this cart
	*
	* @author Valerie Isaksen
	*/

	private function lSelectPayment() {

	  $cart = $this->cart;
 	  $cart->prepareAddressFieldsInCart();
	  if(empty($cart->BT['virtuemart_country_id']))
		{
		  if(count($cart->BTaddress['fields']))
		  {
		  	if (!empty($cart->BTaddress['fields']["virtuemart_country_id"]["virtuemart_country_id"])) {
				 $post['virtuemart_country_id'] = $cart->BTaddress['fields']["virtuemart_country_id"]["virtuemart_country_id"];
				 $cart->saveAddressInCart($post,'BT');
		   	 }
		  }
		}
		
		$paymentModel = VmModel::getModel('Paymentmethod');
		$payments = $paymentModel->getPayments(true, false);
		
		if($cart->virtuemart_paymentmethod_id == 0)
		{
		   if(vmconfig::get("set_automatic_payment") > 0)
		   {
			  $cart->virtuemart_paymentmethod_id = vmconfig::get("set_automatic_payment");
		   }
		   else if(!empty($payments[0]->virtuemart_paymentmethod_id))
		   {
			  $cart->virtuemart_paymentmethod_id = $payments[0]->virtuemart_paymentmethod_id;
		   }
		}
	
		$this->payment_not_found_text='';
		$this->payments_payment_rates=array();

		$this->found_payment_method = 0;
		$selectedPayment = empty($cart->virtuemart_paymentmethod_id) ? 0 : $cart->virtuemart_paymentmethod_id;
		if ($selectedPayment == 0) 
		 {
            $db=JFactory::getDBO();
            $query=$db->getQuery(true);
            $query->select('virtuemart_paymentmethod_id');
            $query->from('#__virtuemart_paymentmethods');
            $query->where("payment_element='klarna_checkout_onepage'");
			$query->where("published=1");
            $db->setQuery($query);
            $pmid = $db->loadResult();
			if($pmid > 0)
			{
	            $cart->virtuemart_paymentmethod_id = $pmid;
				$selectedPayment = $cart->virtuemart_paymentmethod_id;
			}
        }
	

		$this->paymentplugins_payments = array();
		if (!$this->checkPaymentMethodsConfigured()) {
			return;
		}

		if(!class_exists('vmPSPlugin')) require(JPATH_VM_PLUGINS.DS.'vmpsplugin.php');
		JPluginHelper::importPlugin('vmpayment');
		$dispatcher = JDispatcher::getInstance();
		$returnValues = $dispatcher->trigger('plgVmDisplayListFEPayment', array($cart, $selectedPayment,&$this->paymentplugins_payments));

		$this->found_payment_method =count($this->paymentplugins_payments);
		if (!$this->found_payment_method) {
			$link=''; // todo
			$this->payment_not_found_text = vmText::sprintf('COM_VIRTUEMART_CART_NO_PAYMENT_METHOD_PUBLIC', '<a href="'.$link.'" rel="nofollow">'.$link.'</a>');
		}

		$ok = true;
		if ($this->found_payment_method == 0 )  {
			$validUserDataBT = $cart->validateUserData();
			if ($validUserDataBT===-1) {
				if (VmConfig::get('oncheckout_opc', 1)) {
					$ok = false;
				
				}
			}
		}
		
		$paymentsarray=array();
		$paymentsnew=array();
		
		/* this code modified for one page generic to list payment in one page */
		
		foreach($this->paymentplugins_payments as $items) {
			/* this code modified for one page generic to list payment in one page */
			if(is_array($items)) {
				foreach($items as $item) {
				
					$paymentsarray[]=$item;		
				    $tmptext = "";
					$tmptext = strip_tags($item , '<span><input><img>');
					$tmptext =  str_replace("error_div", "error_div opg-hidden ", $tmptext);
					$tmptext =  str_replace("          /*  */                   Please enable JavaScript.        ", "", $tmptext);
					$tmptext =  str_replace("/*", "", $tmptext);
					$tmptext =  str_replace("*/", "", $tmptext);
					$tmptext =  str_replace("Please enable JavaScript.", "", $tmptext);
					$paymentsnew[]= $tmptext;

				}
			} else {
				$paymentsarray[]=$items;
			    $tmptext = strip_tags($items , '<span><input><img>');
				$tmptext =  str_replace("error_div", "error_div opg-hidden ", $tmptext);
				$tmptext =  str_replace("          /*  */                   Please enable JavaScript.        ", "", $tmptext);
				$tmptext =  str_replace("/*", "", $tmptext);
				$tmptext =  str_replace("*/", "", $tmptext);
				$tmptext =  str_replace("Please enable JavaScript.", "", $tmptext);
				$paymentsnew[]= $tmptext;
			}
			
		}
		/* this code modified for one page generic to list payment in one page */
        $this->assignRef('paymentplugins_paymentsnew', $paymentsnew);
        $this->assignRef('paymentplugins_payments', $paymentsarray);
		return $ok;
	}

	private function getTotalInPaymentCurrency() {
		$cart = $this->cart;

		if (empty($cart->virtuemart_paymentmethod_id)) {
			return null;
		}

		if (!$cart->paymentCurrency or ($cart->paymentCurrency==$cart->pricesCurrency)) {
			return null;
		}

		$paymentCurrency = CurrencyDisplay::getInstance($cart->paymentCurrency);
		$totalInPaymentCurrency = $paymentCurrency->priceDisplay( $cart->cartPrices['billTotal'],$cart->paymentCurrency) ;
		$this->currencyDisplay = CurrencyDisplay::getInstance($cart->pricesCurrency);

		return $totalInPaymentCurrency;
	}
	/*
	 * Trigger to place Coupon, payment, shipment advertisement on the cart
	 */
	private function getCheckoutAdvertise() {
		$cart = $this->cart;
		$checkoutAdvertise=array();
		JPluginHelper::importPlugin('vmextended');
		JPluginHelper::importPlugin('vmcoupon');
		JPluginHelper::importPlugin('vmshipment');
		JPluginHelper::importPlugin('vmpayment');
		$dispatcher = JDispatcher::getInstance();
		$returnValues = $dispatcher->trigger('plgVmOnCheckoutAdvertise', array( $cart, &$checkoutAdvertise));
		return $checkoutAdvertise;
}

	private function lOrderDone() {
		$cart = $this->cart;
		$this->display_title = vRequest::getBool('display_title',true);
		//Do not change this. It contains the payment form
		$this->html = vRequest::get('html', vmText::_('COM_VIRTUEMART_ORDER_PROCESSED') );
		//Show Thank you page or error due payment plugins like paypal express
	}

	private function checkPaymentMethodsConfigured() {
		$cart = $this->cart;

		//For the selection of the payment method we need the total amount to pay.
		$paymentModel = VmModel::getModel('Paymentmethod');
		$payments = $paymentModel->getPayments(true, false);
		if (empty($payments)) {

			$text = '';
			$user = JFactory::getUser();
			if($user->authorise('core.admin','com_virtuemart') or $user->authorise('core.manage','com_virtuemart') or VmConfig::isSuperVendor()) {
				$uri = JFactory::getURI();
				$link = $uri->root() . 'administrator/index.php?option=com_virtuemart&view=paymentmethod';
				$text = vmText::sprintf('COM_VIRTUEMART_NO_PAYMENT_METHODS_CONFIGURED_LINK', '<a href="' . $link . '" rel="nofollow">' . $link . '</a>');
			}

			vmInfo('COM_VIRTUEMART_NO_PAYMENT_METHODS_CONFIGURED', $text);

			$tmp = 0;
			$this->assignRef('found_payment_method', $tmp);
			$cart->virtuemart_paymentmethod_id = 0;
			return false;
		}
		return true;
	}

	private function checkShipmentMethodsConfigured() {
		$cart = $this->cart;

		//For the selection of the shipment method we need the total amount to pay.
		$shipmentModel = VmModel::getModel('Shipmentmethod');
		$shipments = $shipmentModel->getShipments();
		if (empty($shipments)) {

			$text = '';
			$user = JFactory::getUser();
			if($user->authorise('core.admin','com_virtuemart') or $user->authorise('core.manage','com_virtuemart') or VmConfig::isSuperVendor()) {
				$uri = JFactory::getURI();
				$link = $uri->root() . 'administrator/index.php?option=com_virtuemart&view=shipmentmethod';
				$text = vmText::sprintf('COM_VIRTUEMART_NO_SHIPPING_METHODS_CONFIGURED_LINK', '<a href="' . $link . '" rel="nofollow">' . $link . '</a>');
			}

			vmInfo('COM_VIRTUEMART_NO_SHIPPING_METHODS_CONFIGURED', $text);

			$tmp = 0;
			$this->assignRef('found_shipment_method', $tmp);
			$cart->virtuemart_shipmentmethod_id = 0;
			return false;
		}
		return true;
	}

	/**
	 * Todo, works only for small stores, we need a new solution there with a bit filtering
	 * For example by time, if already shopper, and a simple search
	 * @return object list of users
	 */
	function getUserList() {
		$cart = $this->cart;

		$result = false;

		if($this->allowChangeShopper){
			$this->adminID = JFactory::getSession()->get('vmAdminID',false);
			if($this->adminID) {
				if(!class_exists('vmCrypt'))
					require(VMPATH_ADMIN.DS.'helpers'.DS.'vmcrypt.php');
				$this->adminID = vmCrypt::decrypt( $this->adminID );
			}
			$superVendor = VmConfig::isSuperVendor($this->adminID);
			if($superVendor){
				$uModel = VmModel::getModel('user');
				$result = $uModel->getSwitchUserList($superVendor,$this->adminID);
			}
		}
		//vmdebug('my user list ',$result);
		if(!$result) $this->allowChangeShopper = false;
		return $result;
	}

	function renderCompleteAddressList(){

		$cart = $this->cart;
		$addressList = false;

		if($cart->user->virtuemart_user_id){
			$addressList = array();
			$newBT = '<a href="index.php'
				.'?option=com_virtuemart'
				.'&view=user'
				.'&task=editaddresscart'
				.'&addrtype=BT'
				. '">'.vmText::_('COM_VIRTUEMART_ACC_BILL_DEF').'</a></br>';
			foreach($cart->user->userInfo as $userInfo){
				$address = $userInfo->loadFieldValues(false);
				if($address->address_type=='BT'){
					$address->virtuemart_userinfo_id = 0;
					$address->address_type_name = $newBT;
					array_unshift($addressList,$address);
				} else {
					$address->address_type_name = '<a href="index.php'
					.'?option=com_virtuemart'
					.'&view=user'
					.'&task=editaddresscart'
					.'&addrtype=ST'
					.'&virtuemart_userinfo_id='.$address->virtuemart_userinfo_id
					. '" rel="nofollow">'.$address->address_type_name.'</a></br>';
					$addressList[] = $address;
				}
			}
			if(count($addressList)==0){
				$addressList[0] = new stdClass();
				$addressList[0]->virtuemart_userinfo_id = 0;
				$addressList[0]->address_type_name = $newBT;
			}

			$_selectedAddress = (
			empty($cart->selected_shipto)
				? $addressList[0]->virtuemart_userinfo_id // Defaults to 1st BillTo
				: $cart->selected_shipto
			);

			$cart->lists['shipTo'] = JHtml::_('select.radiolist', $addressList, 'shipto', null, 'virtuemart_userinfo_id', 'address_type_name', $_selectedAddress);
			$cart->lists['billTo'] = empty($addressList[0]->virtuemart_userinfo_id)? 0 : $addressList[0]->virtuemart_userinfo_id;
		} else {
			$cart->lists['shipTo'] = false;
			$cart->lists['billTo'] = false;
		}
	}

	static public function addCheckRequiredJs(){
		$cart = $this->cart;
		$j='jQuery(document).ready(function(){

    jQuery(".shipto_fields_div").find(":radio").change(function(){
        var form = jQuery("#checkoutFormSubmit");
        jQuery(this).vm2front("startVmLoading");
		document.checkoutForm.submit();
    });
    jQuery(".required").change(function(){
    	var count = 0;
    	var hit = 0;
    	jQuery.each(jQuery(".required"), function (key, value){
    		count++;
    		if(jQuery(this).attr("checked")){
        		hit++;
       		}
    	});
        if(count==hit){
        	jQuery(this).vm2front("startVmLoading");
        	var form = jQuery("#checkoutFormSubmit");
        	//document.checkoutForm.task = "checkout";
			document.checkoutForm.submit();
        }
    });
});';
		vmJsApi::addJScript('autocheck',$j);
	}
}