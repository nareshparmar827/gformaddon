<?php
/**
 * Easily interact with the Authorize.Net AIM API.
 *
 * Example Authorize and Capture Transaction against the Sandbox:
 * <code>
 * <?php require_once 'AuthorizeNet.php'
 * $sale = new AuthorizeNetAIM;
 * $sale->setFields(
 *     array(
 *    'amount' => '4.99',
 *    'card_num' => '411111111111111',
 *    'exp_date' => '0515'
 *    )
 * );
 * $response = $sale->authorizeAndCapture();
 * if ($response->approved) {
 *     echo "Sale successful!"; } else {
 *     echo $response->error_message;
 * }
 * ?>
 * </code>
 *
 * Note: To send requests to the live gateway, either define this:
 * define("AUTHORIZENET_SANDBOX", false);
 *   -- OR --
 * $sale = new AuthorizeNetAIM;
 * $sale->setSandbox(false);
 *
 * @package    AuthorizeNet
 * @subpackage AuthorizeNetAIM
 * @link       http://www.authorize.net/support/AIM_guide.pdf AIM Guide
 */


/**
 * Builds and sends an AuthorizeNet AIM Request.
 *
 * @package    AuthorizeNet
 * @subpackage AuthorizeNetAIM
 */
class AuthorizeNetAIM extends AuthorizeNetRequest
{

    const LIVE_URL = 'https://post.transactionexpress.com/PostMerchantService.svc/CreditCardSale';
    const SANDBOX_URL = 'https://post.cert.transactionexpress.com/PostMerchantService.svc/CreditCardSale';

    /**
     * Holds all the x_* name/values that will be posted in the request.
     * Default values are provided for best practice fields.
     */
    protected $_x_post_fields = array(
        "version" => "3.1",
        "delim_char" => ",",
        "delim_data" => "TRUE",
        "relay_response" => "FALSE",
        "encap_char" => "|",
        );

    /**
     * Only used if merchant wants to send multiple line items about the charge.
     */
    private $_additional_line_items = array();

    /**
     * Only used if merchant wants to send custom fields.
     */
    private $_custom_fields = array();

    /**
     * Checks to make sure a field is actually in the API before setting.
     * Set to false to skip this check.
     */
    public $verify_x_fields = true;

    /**
     * A list of all fields in the AIM API.
     * Used to warn user if they try to set a field not offered in the API.
     */

    private $_all_aim_fields = array("address","allow_partial_auth","amount",
        "auth_code","authentication_indicator", "bank_aba_code","bank_acct_name",
        "bank_acct_num","bank_acct_type","bank_check_number","bank_name",
        "card_code","card_num","cardholder_authentication_value","city","company",
        "country","cust_id","customer_ip","delim_char","delim_data","description",
        "duplicate_window","duty","echeck_type","email","email_customer",
        "encap_char","exp_date","fax","first_name","footer_email_receipt",
        "freight","header_email_receipt","invoice_num","last_name","line_item",
        "login","method","phone","po_num","recurring_billing","relay_response",
        "ship_to_address","ship_to_city","ship_to_company","ship_to_country",
        "ship_to_first_name","ship_to_last_name","ship_to_state","ship_to_zip",
        "split_tender_id","state","tax","tax_exempt","test_request","tran_key",
        "trans_id","type","version","zip"
        );

    /**
     * Do an AUTH_CAPTURE transaction.
     *
     * Required "x_" fields: card_num, exp_date, amount
     *
     * @param string $amount   The dollar amount to charge
     * @param string $card_num The credit card number
     * @param string $exp_date CC expiration date
     *
     * @return AuthorizeNetAIM_Response
     */
    public function authorizeAndCapture($amount = false, $card_num = false, $exp_date = false)
    {
        ($amount ? $this->amount = $amount : null);
        ($card_num ? $this->card_num = $card_num : null);
        ($exp_date ? $this->exp_date = $exp_date : null);
        $this->type = "AUTH_CAPTURE";
        return $this->_sendRequest();
    }

    /**
     * Do a PRIOR_AUTH_CAPTURE transaction.
     *
     * Required "x_" field: trans_id(The transaction id of the prior auth, unless split
     * tender, then set x_split_tender_id manually.)
     * amount (only if lesser than original auth)
     *
     * @param string $trans_id Transaction id to charge
     * @param string $amount   Dollar amount to charge if lesser than auth
     *
     * @return AuthorizeNetAIM_Response
     */
    public function priorAuthCapture($trans_id = false, $amount = false)
    {
        ($trans_id ? $this->trans_id = $trans_id : null);
        ($amount ? $this->amount = $amount : null);
        $this->type = "PRIOR_AUTH_CAPTURE";
        return $this->_sendRequest();
    }

    /**
     * Do an AUTH_ONLY transaction.
     *
     * Required "x_" fields: card_num, exp_date, amount
     *
     * @param string $amount   The dollar amount to charge
     * @param string $card_num The credit card number
     * @param string $exp_date CC expiration date
     *
     * @return AuthorizeNetAIM_Response
     */
    public function authorizeOnly($amount = false, $card_num = false, $exp_date = false)
    {
        ($amount ? $this->amount = $amount : null);
        ($card_num ? $this->card_num = $card_num : null);
        ($exp_date ? $this->exp_date = $exp_date : null);
        $this->type = "AUTH_ONLY";
        return $this->_sendRequest();
    }

    /**
     * Do a VOID transaction.
     *
     * Required "x_" field: trans_id(The transaction id of the prior auth, unless split
     * tender, then set x_split_tender_id manually.)
     *
     * @param string $trans_id Transaction id to void
     *
     * @return AuthorizeNetAIM_Response
     */
    public function void($trans_id = false)
    {
        ($trans_id ? $this->trans_id = $trans_id : null);
        $this->type = "VOID";
        return $this->_sendRequest();
    }

    /**
     * Do a CAPTURE_ONLY transaction.
     *
     * Required "x_" fields: auth_code, amount, card_num , exp_date
     *
     * @param string $auth_code The auth code
     * @param string $amount    The dollar amount to charge
     * @param string $card_num  The last 4 of credit card number
     * @param string $exp_date  CC expiration date
     *
     * @return AuthorizeNetAIM_Response
     */
    public function captureOnly($auth_code = false, $amount = false, $card_num = false, $exp_date = false)
    {
        ($auth_code ? $this->auth_code = $auth_code : null);
        ($amount ? $this->amount = $amount : null);
        ($card_num ? $this->card_num = $card_num : null);
        ($exp_date ? $this->exp_date = $exp_date : null);
        $this->type = "CAPTURE_ONLY";
        return $this->_sendRequest();
    }

    /**
     * Do a CREDIT transaction.
     *
     * Required "x_" fields: trans_id, amount, card_num (just the last 4)
     *
     * @param string $trans_id Transaction id to credit
     * @param string $amount   The dollar amount to credit
     * @param string $card_num The last 4 of credit card number
     *
     * @return AuthorizeNetAIM_Response
     */
    public function credit($trans_id = false, $amount = false, $card_num = false)
    {
        ($trans_id ? $this->trans_id = $trans_id : null);
        ($amount ? $this->amount = $amount : null);
        ($card_num ? $this->card_num = $card_num : null);
        $this->type = "CREDIT";
        return $this->_sendRequest();
    }

    /**
     * Alternative syntax for setting x_ fields.
     *
     * Usage: $sale->method = "echeck";
     *
     * @param string $name
     * @param string $value
     */
    public function __set($name, $value)
    {
        $this->setField($name, $value);
    }

    /**
     * Quickly set multiple fields.
     *
     * Note: The prefix x_ will be added to all fields. If you want to set a
     * custom field without the x_ prefix, use setCustomField or setCustomFields.
     *
     * @param array $fields Takes an array or object.
     */
    public function setFields($fields)
    {
        $array = (array)$fields;
        foreach ($array as $key => $value) {
            $this->setField($key, $value);
        }
    }

    /**
     * Quickly set multiple custom fields.
     *
     * @param array $fields
     */
    public function setCustomFields($fields)
    {
        $array = (array)$fields;
        foreach ($array as $key => $value) {
            $this->setCustomField($key, $value);
        }
    }

    /**
     * Add a line item.
     *
     * @param string $item_id
     * @param string $item_name
     * @param string $item_description
     * @param string $item_quantity
     * @param string $item_unit_price
     * @param string $item_taxable
     */
    public function addLineItem($item_id, $item_name, $item_description, $item_quantity, $item_unit_price, $item_taxable)
    {
        return false;
        $line_item = "";
        $delimiter = "";
        foreach (func_get_args() as $key => $value) {
            $line_item .= $delimiter . $value;
            $delimiter = "<|>";
        }
        $this->_additional_line_items[] = $line_item;
    }

    /**
     * Use ECHECK as payment type.
     */
    public function setECheck($bank_aba_code, $bank_acct_num, $bank_acct_type, $bank_name, $bank_acct_name, $echeck_type = 'WEB')
    {
        $this->setFields(
            array(
            'method' => 'echeck',
            'bank_aba_code' => $bank_aba_code,
            'bank_acct_num' => $bank_acct_num,
            'bank_acct_type' => $bank_acct_type,
            'bank_name' => $bank_name,
            'bank_acct_name' => $bank_acct_type,
            'echeck_type' => $echeck_type,
            )
        );
    }

    /**
     * Set an individual name/value pair. This will append x_ to the name
     * before posting.
     *
     * @param string $name
     * @param string $value
     */
    public function setField($name, $value)
    {
        if ($this->verify_x_fields) {
            if (in_array($name, $this->_all_aim_fields)) {
                $this->_x_post_fields[$name] = $value;
            } else {
                throw new AuthorizeNetException("Error: no field $name exists in the AIM API.
                To set a custom field use setCustomField('field','value') instead.");
            }
        } else {
            $this->_x_post_fields[$name] = $value;
        }
    }

    /**
     * Set a custom field. Note: the x_ prefix will not be added to
     * your custom field if you use this method.
     *
     * @param string $name
     * @param string $value
     */
    public function setCustomField($name, $value)
    {
        $this->_custom_fields[$name] = $value;
    }

    /**
     * Unset an x_ field.
     *
     * @param string $name Field to unset.
     */
    public function unsetField($name)
    {
        unset($this->_x_post_fields[$name]);
    }

    /**
     *
     *
     * @param string $response
     *
     * @return AuthorizeNetAIM_Response
     */
    protected function _handleResponse($response)
    {
        return new AuthorizeNetAIM_Response($response, $this->_x_post_fields['delim_char'], $this->_x_post_fields['encap_char'], $this->_x_post_fields); // $this->_custom_fields
    }

    /**
     * @return string
     */
    protected function _getPostUrl()
    {
        return ($this->_sandbox ? self::SANDBOX_URL : self::LIVE_URL);
    }

    /**
     * Converts the x_post_fields array into a string suitable for posting.
     */
    protected function _setPostString()
    {

        $stateabbr = array(
            "Alabama" => 'AL',
            "Alaska" => 'AK',
            "Arizona" => 'AZ',
            "Arkansas" => 'AR',
            "California" => 'CA',
            "Colorado" => 'CO',
            "Connecticut" => 'CT',
            "Delaware" => 'DE',
            "District Of Columbia" => 'DC',
            "Florida" => 'FL',
            "Georgia" => 'GA',
            "Hawaii" => 'HI',
            "Idaho" => 'ID',
            "Illinois" => 'IL',
            "Indiana" => 'IN',
            "Iowa" => 'IA',
            "Kansas" => 'KS',
            "Kentucky" => 'KY',
            "Louisiana" => 'LA',
            "Maine" => 'ME',
            "Maryland" => 'MD',
            "Massachusetts" => 'MA',
            "Michigan" => 'MI',
            "Minnesota" => 'MN',
            "Mississippi" => 'MS',
            "Missouri" => 'MO',
            "Montana" => 'MT',
            "Nebraska" => 'NE',
            "Nevada" => 'NV',
            "New Hampshire" => 'NH',
            "New Jersey" => 'NJ',
            "New Mexico" => 'NM',
            "New York" => 'NY',
            "North Carolina" => 'NC',
            "North Dakota" => 'ND',
            "Ohio" => 'OH',
            "Oklahoma" => 'OK',
            "Oregon" => 'OR',
            "Pennsylvania" => 'PA',
            "Rhode Island" => 'RI',
            "South Carolina" => 'SC',
            "South Dakota" => 'SD',
            "Tennessee" => 'TN',
            "Texas" => 'TX',
            "Utah" => 'UT',
            "Vermont" => 'VT',
            "Virginia" => 'VA',
            "Washington" => 'WA',
            "West Virginia" => 'WV',
            "Wisconsin" => 'WI',
            "Wyoming" => 'WY',
            'Armed Forces Americas' => 'AA',
            'Armed Forces Europe' => 'AE',
            'Armed Forces Pacific' => 'AP',
        );

        $state = isset($stateabbr[$this->_x_post_fields['state']]) ? $stateabbr[$this->_x_post_fields['state']] : '';
        $this->_post_string =
          "GatewayID=" . $this->_api_login .
          "&RegKey=" . $this->_transaction_key .
          "&IndustryCode=2" .
          "&Amount="          . ($this->_x_post_fields['amount'] * 100) .
          "&AccountNumber="       . $this->_x_post_fields['card_num'] .
          "&ExpirationDate=" . substr($this->_x_post_fields['exp_date'], -2) . substr($this->_x_post_fields['exp_date'], 0, 2) .
          "&CVV2="         . $this->_x_post_fields['card_code'] .
          "&FullName=" . $this->_x_post_fields['first_name'] . ' ' . $this->_x_post_fields['last_name'] .
          "&Address1=" . $this->_x_post_fields['address'] .
                // "&Address2="      . $this->_x_post_fields[''] .
          "&City=" . $this->_x_post_fields['city'] .
          "&State=" . $state .
          "&Zip=" . $this->_x_post_fields['zip'] .
                //"&PhoneNumber="     . $this->_x_post_fields[''] .
          "&Email="     . $this->_x_post_fields['email'] .
          "&CustRefID=" . $this->_x_post_fields['invoice_num'];


       // error_log($this->_post_string);

        /*
        foreach ($this->_x_post_fields as $key => $value) {
            $this->_post_string .= "x_$key=" . urlencode($value) . "&";
        }
        // Add line items
        foreach ($this->_additional_line_items as $key => $value) {
            $this->_post_string .= "x_line_item=" . urlencode($value) . "&";
        }
        // Add custom fields
        foreach ($this->_custom_fields as $key => $value) {
            $this->_post_string .= "$key=" . urlencode($value) . "&";
        }

        $this->_post_string = rtrim($this->_post_string, "& ");
        */
    }
}

/**
 * Parses an AuthorizeNet AIM Response.
 *
 * @package    AuthorizeNet
 * @subpackage AuthorizeNetAIM
 */
class AuthorizeNetAIM_Response extends AuthorizeNetResponse
{
    private $_response_array = array(); // An array with the split response.

    /**
     * Constructor. Parses the AuthorizeNet response string.
     *
     * @param string $response      The response from the AuthNet server.
     * @param string $delimiter     The delimiter used (default is ",")
     * @param string $encap_char    The encap_char used (default is "|")
     * @param array  $custom_fields Any custom fields set in the request.
     */
    public function __construct($response, $delimiter, $encap_char, $custom_fields)
    {

        if ($response) {

            $response = substr($response, 1, -1);
            parse_str( $response, $response);

            // Put back into 1.00 format instead of 0000100.
            $response['Amount'] = $response['Amount'] / 100;

            /* We manually passed in the form data to keep track of it. First Trans doesn't respond back with all that data.. */
            $form_data = $custom_fields;
            $one_digit = substr($form_data['card_num'], 0, 1);
            $two_digit = substr($form_data['card_num'], 0, 2);

            /* figure out card type used.. */
            $card_type = 'Diners Club';

            if ($one_digit == 4) {
                $card_type = 'Visa';
            }
            else if ($two_digit >= 50 && $two_digit <= 55) {
                $card_type = 'MasterCard';
            }
            else if ($two_digit == 35) {
                $card_type = 'JCB';
            }
            else if ($two_digit == 34 || $two_digit == 37) {
                $card_type = 'AMEX';
            }
            else if ($one_digit == 6) {
                $card_type = 'Discover';
            }

            unset($form_data['card_num']);
            unset($form_data['exp_date']);
            unset($form_data['card_code']);

/*
                 ( [    ResponseCode] => 14
        [tranNr] => 000003122751
        [PostDate] => 2014-02-27T08:03:38.000
        [Amount] => 000000000010
        [AmtDueRemaining] => 0
        [CardBalance] =>
        [Auth] =>
        [error] => Invalid card number
        */




            // Split Array
            $this->response = $response;
            $this->_response_array = $response;

            // Set all fields
            $this->response_code        = $this->_response_array['ResponseCode'];
            $this->response_subcode     = 0;
            $this->response_reason_code = $this->_response_array['ResponseCode'];
            $this->response_reason_text = $this->getError($this->_response_array['ResponseCode']);
            $this->authorization_code   = $this->_response_array['Auth'];
            $this->avs_response         = $this->_response_array['AVSCode'];
            $this->transaction_id       = $this->_response_array['tranNr'];
            $this->invoice_number       = $form_data['invoice_num'];
            $this->description          = $form_data['description'];
            $this->amount               = $this->_response_array['Amount'];
            $this->method               = 'CC';
            $this->transaction_type     = $form_data['type'];
            $this->customer_id          = '';
            $this->first_name           = $form_data['first_name'];
            $this->last_name            = $form_data['last_name'];
            $this->company              = '';
            $this->address              = $form_data['address'];
            $this->city                 = $form_data['city'];
            $this->state                = $form_data['state'];
            $this->zip_code             = $form_data['zip'];
            $this->country              = $form_data['country'];
            $this->phone                = '';
            $this->fax                  = '';
            $this->email_address        = $form_data['email'];
            $this->ship_to_first_name   = '';
            $this->ship_to_last_name    = '';
            $this->ship_to_company      = '';
            $this->ship_to_address      = '';
            $this->ship_to_city         = '';
            $this->ship_to_state        = '';
            $this->ship_to_zip_code     = '';
            $this->ship_to_country      = '';
            $this->tax                  = '';
            $this->duty                 = '';
            $this->freight              = '';
            $this->tax_exempt           = '';
            $this->purchase_order_number= '';
            $this->md5_hash             = '';
            $this->card_code_response   = $this->_response_array['CVV2Response'];
            $this->cavv_response        = $this->_response_array['CVV2Response'];
            $this->account_number       = '';
            $this->card_type            = $card_type;
            $this->split_tender_id      = '';
            $this->requested_amount     = '';
            $this->balance_on_card      = $this->_response_array['CardBalance'];

            $this->approved = $response['Auth'] !="" && $response['Auth'] != "Declined" && !is_null($response['Auth']);
            $this->declined = !$this->approved;
            $this->error    = 0; // !$this->approved
            $this->held     = false; // ($this->response_code == self::HELD);

            // Set custom fields
            /*
            if ($count = count($custom_fields)) {
                $custom_fields_response = array_slice($this->_response_array, -$count, $count);
                $i = 0;
                foreach ($custom_fields as $key => $value) {
                    $this->$key = $custom_fields_response[$i];
                    $i++;
                }
            }
            */

            if ($this->error) {

                $this->error_message = "TransFirst Error:
                    Response Code: ".$this->response_code."
                    Reason Text: ".$this->getError($this->_response_array['ResponseCode'])."
                ";
            }

        } else {
            $this->approved = false;
            $this->error = true;
            $this->error_message = "Error connecting";
        }
    }

/**
 * Responses, Error & Not...
 * @param  [type] $code [description]
 * @return [type]       [description]
 */
   function getError($code) {

    $errors = array(
    '00' => "Approved or completed successfully",
    '01' => "Refer to card issuer",
    '02' => "Refer to card issuer, special condition",
    '03' => "Invalid merchant",
    '04' => "Pick-up card",
    '05' => "Do not honor",
    '06' => "Error",
    '07' => "Pick-up card, special condition",
    '08' => "Honor with identification (this is a decline response when a card not present transaction) If you receive an approval in a card not present environment, you will need to void the transaction.",
    '09' => "Request in progress",
    '10' => "Approved, partial authorization",
    11 => "VIP Approval (this is a decline response for a card not present transaction)",
    12 => "Invalid transaction",
    13 => "Invalid amount",
    14 => "Invalid card number",
    15 => "No such issuer",
    16 => "Approved, update track 3",
    17 => "Customer cancellation",
    18 => "Customer dispute",
    19 => "Re-enter transaction",
    20 => "Invalid response",
    21 => "No action taken",
    22 => "Suspected malfunction",
    23 => "Unacceptable transaction fee",
    24 => "File update not supported",
    25 => "Unable to locate record",
    26 => "Duplicate record",
    27 => "File update field edit error",
    28 => "File update file locked",
    29 => "File update failed",
    30 => "Format error",
    31 => "Bank not supported",
    32 => "Completed partially",
    33 => "Expired card, pick-up",
    34 => "Suspected fraud, pick-up",
    35 => "Contact acquirer, pick-up",
    36 => "Restricted card, pick-up",
    37 => "Call acquirer security, pick-up",
    38 => "PIN tries exceeded, pick-up",
    39 => "No credit account",
    40 => "Function not supported",
    41 => "Lost card, pick-up",
    42 => "No universal account",
    43 => "Stolen card, pick-up",
    44 => "No investment account",
    45 => "Account closed",
    46 => "Identification required",
    47 => "Identification cross-check required",
    48 => "No customer record",
    49 => "Reserved for future Realtime use",
    50 => "Reserved for future Realtime use",
    51 => "Not sufficient funds",
    52 => "No checking account",
    53 => "No savings account",
    54 => "Expired card",
    55 => "Incorrect PIN",
    56 => "No card record",
    57 => "Transaction not permitted to cardholder",
    58 => "Transaction not permitted on terminal",
    59 => "Suspected fraud",
    60 => "Contact acquirer",
    61 => "Exceeds withdrawal limit",
    62 => "Restricted card",
    63 => "Security violation",
    64 => "Original amount incorrect",
    65 => "Exceeds withdrawal frequency",
    66 => "Call acquirer security",
    67 => "Hard capture",
    68 => "Response received too late",
    69 => "Advice received too late",
    70 => "Reserved for future use",
    71 => "Reserved for future Realtime use",
    72 => "Reserved for future Realtime use",
    73 => "Reserved for future Realtime use",
    74 => "Reserved for future Realtime use",
    75 => "PIN tries exceeded",
    76 => "Reversal: Unable to locate previous message (no match on Retrieval Reference Number)/ Reserved for future Realtime use",
    77 => "Previous message located for a repeat or reversal, but repeat or reversal data is inconsistent with original message/ Intervene, bank approval required",
    78 => "Invalid/non-existent account – Decline (MasterCard specific)/ Intervene, bank approval required for partial amount",
    79 => "Already reversed (by Switch)/ Reserved for client-specific use (declined)",
    80 => "No financial Impact (Reserved for declined debit)/ Reserved for client-specific use (declined)",
    81 => "PIN cryptographic error found by the Visa security module during PIN decryption/ Reserved for client-specific use (declined)",
    82 => "Incorrect CVV/ Reserved for client-specific use (declined)",
    83 => "Unable to verify PIN/ Reserved for client-specific use (declined)",
    84 => "Invalid Authorization Life Cycle – Decline (MasterCard) or Duplicate Transaction Detected (Visa)/ Reserved for client-specific use (declined)",
    85 => "No reason to decline a request for Account Number Verification or Address Verification/ Reserved for client-specific use (declined)",
    86 => "Cannot verify PIN/ Reserved for client-specific use (declined)",
    87 => "Reserved for client-specific use (declined)",
    88 => "Reserved for client-specific use (declined)",
    89 => "Reserved for client-specific use (declined)",
    90 => "Cut-off in progress",
    91 => "Issuer or switch inoperative",
    92 => "Routing error",
    93 => "Violation of law",
    94 => "Duplicate Transmission (Integrated Debit and MasterCard)",
    95 => "Reconcile error",
    96 => "System malfunction",
    97 => "Reserved for future Realtime use",
    '98' => "Exceeds cash limit",
    '99' => "Reserved for future Realtime use",
    '0A' => "Reserved for future Realtime use",
    'A0' => "Reserved for future Realtime use",
    'A1' => "ATC not incremented",
    'A2' => "ATC limit exceeded",
    'A3' => "ATC configuration error",
    'A4' => "CVR check failure",
    'A5' => "CVR configuration error",
    'A6' => "TVR check failure",
    'A7' => "TVR configuration error",
    'A8 to BZ' => "Reserved for future Realtime use",
    'B1' => "Surcharge amount not permitted on Visa cards or EBT Food Stamps/ Reserved for future Realtime use",
    'B2' => "Surcharge amount not supported by debit network issuer/ Reserved for future Realtime use",
    'C0' => "Unacceptable PIN",
    'C1' => "PIN Change failed",
    'C2' => "PIN Unblock failed",
    'C3 to D0' => "Reserved for future Realtime use",
    'D1' => "MAC Error",
    'D2 to E0' => "Reserved for future Realtime use",
    'E1' => "Prepay error",
    'E2 to MZ' => "Reserved for future Realtime use",
    'N0 to ZZ' => "Reserved for client-specific use (declined)",
    'N0' => "Force STIP/ Reserved for client-specific use (declined)",
    'N3' => "Cash service not available/ Reserved for client-specific use (declined)",
    'N4' => "Cash request exceeds Issuer limit/ Reserved for client-specific use (declined)",
    'N5' => "Ineligible for re-submission/ Reserved for client-specific use (declined)",
    'N7' => "Decline for CVV2 failure/ Reserved for client-specific use (declined)",
    'N8' => "Transaction amount exceeds preauthorized approval amount/ Reserved for client-specific use (declined)",
    'P0' => "Approved; PVID code is missing, invalid, or has expired",
    'P1' => "Declined; PVID code is missing, invalid, or has expired/ Reserved for client-specific use (declined)",
    'P2' => "Invalid biller Information/ Reserved for client-specific use (declined)/ Reserved for client-specific use (declined)",
    'R0' => "The transaction was declined or returned, because the cardholder requested that payment of a specific recurring or installment payment transaction be stopped/ Reserved for client-specific use (declined)",
    'R1' => "The transaction was declined or returned, because the cardholder requested that payment of all recurring or installment payment transactions for a specific merchant account be stopped/ Reserved for client-specific use (declined)",
    'Q1' => "Card Authentication failed/ Reserved for client-specific use (declined)",
    'XA' => "Forward to Issuer/ Reserved for client-specific use (declined)",
    'XD' => "Forward to Issuer/ Reserved for client-specific use (declined)",
    );


      return isset($errors[$code]) ? $errors[$code] : 'Declined';
   }

}
