<?php
namespace SALT;

use \Exception;
class CreditCardReceipt {
    private $params = null;
    private $approved = false;
    private $transactionId = null;
    private $orderId = null;
    private $processedDateTime = null; // as a string
    private $processedDateTimestamp = null; // as an int (can apply your own formatting to this value)
    private $errorCode = null;
    private $errorMessage = null;
    private $debugMessage = null;
    private $approvalInfo = null;
    private $avsResponse = null;
    private $cvv2Response = null;
    private $response = null;
    private $periodicPurchaseInfo = null;
    private $fraudScore = null;
    private $fraudDecision = null;

    // constructor parses response from gateway into this object
    public function __construct( $response = null ) {
        if ( $response == null ) {
            return;
        }



        // parse response into param associative array

        $this->response = $response;
        $lines = explode( "\n" , $this->response );
        $this->params = array();

        $this->params["ERROR_CODE"] = null;
        $this->params["ERROR_MESSAGE"] = null;
        $this->params["DEBUG_MESSAGE"] = null;
        $this->params["AVS_RESPONSE_AVAILABLE"] = null;
        $this->params["CVV2_RESPONSE_AVAILABLE"] = null;
        $this->params["AUTHORIZED_AMOUNT"] = null;
        $this->params["APPROVAL_CODE"] = null;
        $this->params["TRACE_NUMBER"] = null;
        $this->params["REFERENCE_NUMBER"] = null;
        $this->params["PERIODIC_TRANSACTION_ID"] = null;
        $this->params["TRANSACTION_ID"]= null;
        $this->params["ORDER_ID"] = null;
        $this->params["PROCESSED_DATE"] = null;
        $this->params["PROCESSED_TIME"] = null;
        $this->params["APPROVED"] = null;



        $size = count( $lines );


        for ( $i = 0; $i < $size; $i++ ) {

            try{



                list( $paramKey, $paramValue ) = array_pad( explode( "=", $lines[$i], 2 ), 2, null );

                //list($paramKey, $paramValue) = explode("=", $lines[$i]);
            }
            catch( \Exception $e ) {
                throw new SaltError( $e->getMessage() );
            }

            $this->params[$paramKey] = $paramValue;



        }


        if ( isset( $this->params["ERROR_CODE"] ) && $this->params["ERROR_CODE"] != '0' && isset( $this->params["ERROR_MESSAGE"] ) ) {
            throw new SaltError( $this->params['ERROR_MESSAGE'] . ": " . constant( $this->params["ERROR_MESSAGE"] ) );
        }
        
        // Import the response parameters into this object.
        if ( isset( $this->params["APPROVED"] ) ) {
            $this->approved = $this->params["APPROVED"] == 'true';
        } 

        if ( isset( $this->params["TRANSACTION_ID"] ) ) {

            $this->transactionId = $this->params["TRANSACTION_ID"];
        }


        if ( isset( $this->params["ORDER_ID"] ) ) {
            $this->orderId = $this->params["ORDER_ID"];
        }

        if ( isset( $this->params["PROCESSED_DATE"] ) ) {
            // returned date time is in yymmddhhiiss format
            $processedDate = $this->params["PROCESSED_DATE"];
        }

        if ( isset( $this->params["PROCESSED_TIME"] ) ) {
            $processedTime = $this->params["PROCESSED_TIME"];
        }



        if ( $processedDate != null && $processedTime != null ) {
            $year = substr( $processedDate, 0, 2 );
            $month = substr( $processedDate, 2, 2 );
            $day = substr( $processedDate, 4, 2 );
            $hour = substr( $processedTime, 0, 2 );
            $minute = substr( $processedTime, 2, 2 );
            $second = substr( $processedTime, 4, 2 );
            $this->processedDateTimestamp = strtotime( $year . '-' . $month . '-' . $day . ' ' . $hour . ':' . $minute . ':' . $second );
            $this->processedDateTime = date( 'r', $this->processedDateTimestamp );
        } else {
            $this->processedDateTime = null;
        }
        if ( isset( $this->params["ERROR_CODE"] ) ) {
            $this->errorCode = $this->params["ERROR_CODE"];
        }


        if ( isset( $this->params["ERROR_MESSAGE"] ) ) {
            $this->errorMessage = $this->params["ERROR_MESSAGE"];
        }

        if ( isset( $this->params["DEBUG_MESSAGE"] ) ) {
            $this->debugMessage = $this->params["DEBUG_MESSAGE"];
        }


        // parse the approval info

       
        if ( isset( $this->params["AUTHORIZED_AMOUNT"] ) && isset( $this->params["APPROVAL_CODE"] ) && isset( $this->params["TRACE_NUMBER"] ) && isset( $this->params["REFERENCE_NUMBER"] ) ) {

            if ( $this->approved ) {
                $this->approvalInfo = new ApprovalInfo(

                    $this->params["AUTHORIZED_AMOUNT"],
                    $this->params["APPROVAL_CODE"],
                    $this->params["TRACE_NUMBER"],
                    $this->params["REFERENCE_NUMBER"] );

            } else {
                $this->approvalInfo = null;
            }

        }

        if ( isset( $this->params["AVS_RESPONSE_AVAILABLE"] ) ) {
            // parse the AVS response
            $avsResponseAvailable = $this->params["AVS_RESPONSE_AVAILABLE"];
            if ( $avsResponseAvailable != null && $avsResponseAvailable ) {
                $avsErrorCode = null;
                $avsErrorMessage = null;
                if ( array_key_exists( "AVS_ERROR_CODE", $this->params ) ) {
                    $avsErrorCode = $this->params["AVS_ERROR_CODE"];
                }
                if ( array_key_exists( "AVS_ERROR_MESSAGE", $this->params ) ) {
                    $avsErrorMessage = $this->params["AVS_ERROR_MESSAGE"];
                }
            }
            else {
                throw new SaltError( UNDEFINED_AVS_RESPONSE );
            }

            if ( isset( $this->params["AVS_RESPONSE_CODE"] )===false ) {
                throw new SaltError( UNDEFINED_AVS_RESPONSE_CODE );
            }
            if ( isset( $this->params["STREET_MATCHED"] )===false ) {
                throw new SaltError( UNDEFINED_STREET_MATCHED );
            }
            if ( isset( $this->params["ZIP_MATCHED"] )===false ) {
                throw new SaltError( UNDEFINED_ZIP_MATCHED );
            }

            if ( isset( $this->params["AVS_RESPONSE_CODE"] ) && isset( $this->params["STREET_MATCHED"] ) && isset( $this->params["ZIP_MATCHED"] )
                && isset( $this->params["ZIP_TYPE"] ) ) {
                $this->avsResponse = new AvsResponse(
                    $this->params["AVS_RESPONSE_CODE"],
                    $this->params["STREET_MATCHED"],
                    $this->params["ZIP_MATCHED"],
                    $this->params["ZIP_TYPE"],
                    $avsErrorCode,
                    $avsErrorMessage );
            } else {
                $this->avsResponse = null;
            }
        }



        if ( isset( $this->params["CVV2_RESPONSE_MESSAGE"] ) && isset( $this->params["CVV2_RESPONSE_CODE"] ) ) {


            // parse the CVV2 response
            $cvv2ResponseAvailable = $this->params["CVV2_RESPONSE_AVAILABLE"];


            if ( $cvv2ResponseAvailable != null && $cvv2ResponseAvailable ) {
                $this->cvv2Response = new Cvv2Response(
                    $this->params["CVV2_RESPONSE_CODE"],
                    $this->params["CVV2_RESPONSE_MESSAGE"] );
            } else {
                $this->cvv2Response = null;
            }


        }

        if ( isset( $this->params["FRAUD_SCORE"] ) ) {
            $this->fraudScore = $this->params["FRAUD_SCORE"];
        }

        if ( isset( $this->params["FRAUD_DECISION"] ) ) {
            $this->fraudDecision = $this->params["FRAUD_DECISION"];
        }


        // parse periodic purchase info
        $periodicPurchaseId = $this->params["PERIODIC_TRANSACTION_ID"];
        if ( $periodicPurchaseId != null ) {
            $periodicPurchaseState = $this->params["PERIODIC_TRANSACTION_STATE"];
            $periodicNextPaymentDate = null;
            if ( array_key_exists( "PERIODIC_NEXT_PAYMENT_DATE", $this->params ) ) {
                $periodicNextPaymentDate = $this->params["PERIODIC_NEXT_PAYMENT_DATE"];
            }
            $periodicLastPaymentId = null;
            if ( array_key_exists( "PERIODIC_LAST_PAYMENT_ID", $this->params ) ) {
                $periodicLastPaymentId = $this->params["PERIODIC_LAST_PAYMENT_ID"];
            }
            $this->periodicPurchaseInfo = new PeriodicPurchaseInfo( $periodicPurchaseId, $periodicPurchaseState, null, null, null, null, null, null, $periodicNextPaymentDate, $periodicLastPaymentId );
        } else {
            $this->periodicPurchaseInfo = null;
        }


    }

    // returns an error-only receipt (used when unable to connect to
    // gateway or process request).
    function errorOnlyReceipt( $errorCode, $errorMessage = null, $debugMessage = null ) {
        $theReceipt = new CreditCardReceipt();
        $theReceipt->errorCode = $errorCode;
        $theReceipt->errorMessage = $errorMessage;
        $theReceipt->debugMessage = $debugMessage;
        $theReceipt->processedDateTimestamp = time();
        $theReceipt->processedDateTime = date( 'r', $theReceipt->processedDateTimestamp );
        return $theReceipt;
    }

    public function __get( $property ) {
        if ( property_exists( $this, $property ) ) {
            return $this->$property;
        }

    }

    public function __set( $property, $value ) {
        if ( property_exists( $this, $property ) ) {
            $this->$property = $value;
        }
        return $this;
    }
    function __toString() {

        if ( isset( $params ) && isset( $approved ) && isset( $transactionId ) && isset( $orderId )
            && isset( $processedDateTime ) && isset( $processedDateTimestamp ) && isset( $errorCode ) && isset( $errorMessage ) && isset( $debugMessage )  && isset( $approvalInfo )  && isset( $response ) && isset( $avsResponse ) && isset( $cvv2Response ) && isset( $periodicPurchaseInfo ) && isset( $fraudScore ) && isset( $fraudDecision ) )
            return $params.$approved.$transactionId.$orderId.$processedDateTime.$processedDateTimestamp.$errorCode.$errorMessage.$debugMessage.$approvalInfo.$avsResponse.$cvv2Response.$response.$periodicPurchaseInfo.$fraudScore.$fraudDecision;
    }


} // CreditCardReceipt
