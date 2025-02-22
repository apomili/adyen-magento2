<?php
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;

class CheckoutResponseValidator extends AbstractValidator
{
    /**
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    private $adyenLogger;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    private $adyenHelper;

    /**
     * CheckoutResponseValidator constructor.
     *
     * @param \Magento\Payment\Gateway\Validator\ResultInterfaceFactory $resultFactory
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     */
    public function __construct(
        \Magento\Payment\Gateway\Validator\ResultInterfaceFactory $resultFactory,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Adyen\Payment\Helper\Data $adyenHelper
    ) {
        $this->adyenLogger = $adyenLogger;
        $this->adyenHelper = $adyenHelper;
        parent::__construct($resultFactory);
    }

    /**
     * @param array $validationSubject
     * @return \Magento\Payment\Gateway\Validator\ResultInterface
     */
    public function validate(array $validationSubject)
    {
        $response = \Magento\Payment\Gateway\Helper\SubjectReader::readResponse($validationSubject);
        $paymentDataObjectInterface = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($validationSubject);
        $payment = $paymentDataObjectInterface->getPayment();

        $payment->setAdditionalInformation('3dActive', false);
        $isValid = true;
        $errorMessages = [];

        // validate result
        if (!empty($response['resultCode'])) {
            $resultCode = $response['resultCode'];
            $payment->setAdditionalInformation('resultCode', $resultCode);

            if (!empty($response['action'])) {
                $payment->setAdditionalInformation('action', $response['action']);
            }

            if (!empty($response['additionalData'])) {
                $payment->setAdditionalInformation('additionalData', $response['additionalData']);
            }

            if (!empty($response['pspReference'])) {
                $payment->setAdditionalInformation('pspReference', $response['pspReference']);
            }

            if (!empty($response['paymentData'])) {
                $payment->setAdditionalInformation('adyenPaymentData', $response['paymentData']);
            }

            if (!empty($response['details'])) {
                $payment->setAdditionalInformation('details', $response['details']);
            }

            if (!empty($response['donationToken'])) {
                $payment->setAdditionalInformation('donationToken', $response['donationToken']);
            }

            switch ($resultCode) {
                case "Authorised":
                case "Received":

                // Save cc_type if available in the response
                if (!empty($response['additionalData']['paymentMethod'])) {
                    $ccType = $this->adyenHelper->getMagentoCreditCartType(
                        $response['additionalData']['paymentMethod']
                    );
                    $payment->setAdditionalInformation('cc_type', $ccType);
                    $payment->setCcType($ccType);
                }
                break;
                case "IdentifyShopper":
                case "ChallengeShopper":
                case "PresentToShopper":
                case 'Pending':
                case "RedirectShopper":
                    // nothing extra
                    break;
                case "Refused":
                    $errorMsg = __('The payment is REFUSED.');
                    // this will result the specific error
                    throw new \Magento\Framework\Exception\LocalizedException(__($errorMsg));
                    break;
                default:
                    $errorMsg = __('Error with payment method please select different payment method.');
                    throw new \Magento\Framework\Exception\LocalizedException(__($errorMsg));
                    break;
            }
        } else {
            $errorMsg = __('Error with payment method please select different payment method.');

            if (!empty($response['error'])) {
                $this->adyenLogger->error($response['error']);
            }

            throw new \Magento\Framework\Exception\LocalizedException(__($errorMsg));
        }

        return $this->createResult($isValid, $errorMessages);
    }
}
