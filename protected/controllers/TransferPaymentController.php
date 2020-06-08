<?php
/**
 * =======================================
 * ###################################
 * MagnusBilling
 *
 * @package MagnusBilling
 * @author Adilson Leffa Magnus.
 * @copyright Copyright (C) 2005 - 2016 MagnusBilling. All rights reserved.
 * ###################################
 *
 * This software is released under the terms of the GNU Lesser General Public License v2.1
 * A copy of which is available from http://www.gnu.org/copyleft/lesser.html
 *
 * Please submit bug reports, patches, etc to https://github.com/magnusbilling/mbilling/issues
 * =======================================
 * Magnusbilling.com <info@magnusbilling.com>
 *
 */

/*
add the cron to check the BDService transaction status
echo "
 * * * * * php /var/www/html/mbilling/cron.php bdservice
" >> /var/spool/cron/root
 */

class TransferPaymentController extends Controller
{
    private $url;
    private $amounts    = 0;
    private $user_cost  = 0;
    private $agent_cost = 0;
    private $login;
    private $token;
    private $currency;
    private $send_credit_id;
    private $user_profit;
    private $test = false;
    private $cost;
    private $showprice;
    private $sell_price;
    private $local_currency;
    public $modelTransferToMobile = array();
    public $operator_name;
    private $number;
    public function init()
    {

        $this->modelTransferToMobile = new TransferToMobile();

        $this->instanceModel = new User;
        $this->abstractModel = User::model();
        parent::init();

        $this->login    = $this->config['global']['fm_transfer_to_username'];
        $this->token    = $this->config['global']['fm_transfer_to_ token'];
        $this->currency = $this->config['global']['fm_transfer_currency'];
        $this->url      = 'https://fm.transfer-to.com/cgi-bin/shop/topup?';

        $this->modelTransferToMobile = TransferToMobile::model()->findByPk((int) Yii::app()->session['id_user']);

    }

    public function actionIndex($asJson = true, $condition = null)
    {

        $this->modelTransferToMobile->method = "Payment";

        //select country
        if (!isset($_POST['TransferToMobile']['country'])) {

            $this->render('selectCountry', array(
                'modelTransferToMobile' => $this->modelTransferToMobile,

            ));
            return;
        }
        //select type
        if (!isset($_POST['TransferToMobile']['type'])) {

            $this->modelTransferToMobile->country = $_POST['TransferToMobile']['country'];
            $types                                = [];

            $types["Prepaid_Electricity"] = "Prepaid Electricity";
            $types["Bill_Electricity"]    = "Bill Electricity";

            $this->render('selectType', array(
                'modelTransferToMobile' => $this->modelTransferToMobile,
                'types'                 => $types,
            ));

            return;
        }

        if ($_POST['TransferToMobile']['type'] == 'Bill_Electricity') {
            $this->bill_electricity();
        } else {
            $this->prepaid_electricity();
        }
    }
    public function bill_electricity()
    {

        /*

        Selxe, 3:57 PM
        For Bill Electricity:
        Select Method: Payment
        Select Country: Senegal
        Select Payment type: Bill Electricity
        Insert Bill date: 01/02/2020
        Insert Bill Number: 1234567
        Insert Bill Amount (XOF): 5000
        Show Paid Amount (EUR): 10 (converted to EUR)
        Insert mobile number: 221771234567

         */

        //select amount
        if (!isset($_POST['TransferToMobile']['creationdate'])) {

            $this->modelTransferToMobile->country      = $_POST['TransferToMobile']['country'];
            $this->modelTransferToMobile->type         = $_POST['TransferToMobile']['type'];
            $this->modelTransferToMobile->creationdate = date('Y-m-d');

            $this->getAmountBill();

            $this->render('insertDataBill', array(
                'modelTransferToMobile' => $this->modelTransferToMobile,
            ));

            return;
        }

        $this->modelTransferToMobile->country      = $_POST['TransferToMobile']['country'];
        $this->modelTransferToMobile->type         = $_POST['TransferToMobile']['type'];
        $this->modelTransferToMobile->creationdate = $_POST['TransferToMobile']['creationdate'];
        $this->modelTransferToMobile->number       = $_POST['TransferToMobile']['number'];
        $this->modelTransferToMobile->bill_amount  = $_POST['TransferToMobile']['bill_amount'];

        if (strlen($_POST['TransferToMobile']['number']) < 5) {

            $this->modelTransferToMobile->addError('number', Yii::t('yii', 'Invalid number'));
        }

        if (!is_numeric($_POST['TransferToMobile']['bill_amount']) || $_POST['TransferToMobile']['bill_amount'] < 1) {
            $this->modelTransferToMobile->addError('bill_amount', Yii::t('yii', 'Bill amount need be numeric > 1'));
        }

        if (count($this->modelTransferToMobile->getErrors())) {

            $this->render('insertDataBill', array(
                'modelTransferToMobile' => $this->modelTransferToMobile,
            ));
            return;
        }

        if (!isset($_POST['TransferToMobile']['confirmed'])) {
            $this->render('confirmBill', array(
                'modelTransferToMobile' => $this->modelTransferToMobile,
            ));
            return;
        }
        $this->confirmRefillBill();

    }

    public function prepaid_electricity()
    {

        //select amount
        if (!isset($_POST['TransferToMobile']['amountValuesBDT'])) {

            $this->modelTransferToMobile->country = $_POST['TransferToMobile']['country'];
            $this->modelTransferToMobile->type    = $_POST['TransferToMobile']['type'];

            $this->getAmount();

            $this->render('selectAmount', array(
                'modelTransferToMobile' => $this->modelTransferToMobile,
            ));

            return;
        }

        if (isset($_POST['amountValues'])) {
            $_POST['TransferToMobile']['amountValues'] = $_POST['amountValues'];
        }

        if (isset($_POST['TransferToMobile']['amountValuesBDT']) && $_POST['TransferToMobile']['amountValuesBDT'] > 0) {
            $this->modelTransferToMobile->amountValues = Yii::app()->session['interval_product_id'];
            $_POST['TransferToMobile']['amountValues'] = Yii::app()->session['interval_product_id'];
        } else {

            $this->modelTransferToMobile->amountValues    = Yii::app()->session['interval_product_id']    = $_POST['TransferToMobile']['amountValues'];
            $modelSendCreditProducts                      = SendCreditProducts::model()->findByPk((int) $_POST['TransferToMobile']['amountValues']);
            $_POST['TransferToMobile']['amountValuesBDT'] = $modelSendCreditProducts->product;
        }

        //select meter
        if (!isset($_POST['TransferToMobile']['meter'])) {
            $this->modelTransferToMobile->country         = $_POST['TransferToMobile']['country'];
            $this->modelTransferToMobile->type            = $_POST['TransferToMobile']['type'];
            $this->modelTransferToMobile->amountValues    = $_POST['TransferToMobile']['amountValues'];
            $this->modelTransferToMobile->amountValuesBDT = $_POST['TransferToMobile']['amountValuesBDT'];

            $this->render('insertMeter', array(
                'modelTransferToMobile' => $this->modelTransferToMobile,
            ));

            return;
        }

        //select meter
        if (!isset($_POST['TransferToMobile']['number'])) {

            $this->modelTransferToMobile->country         = $_POST['TransferToMobile']['country'];
            $this->modelTransferToMobile->type            = $_POST['TransferToMobile']['type'];
            $this->modelTransferToMobile->amountValues    = $_POST['TransferToMobile']['amountValues'];
            $this->modelTransferToMobile->amountValuesBDT = $_POST['TransferToMobile']['amountValuesBDT'];
            $this->modelTransferToMobile->meter           = $_POST['TransferToMobile']['meter'];

            $this->render('insertNumber', array(
                'modelTransferToMobile' => $this->modelTransferToMobile,
            ));

            return;
        }

        $this->modelTransferToMobile->country         = $_POST['TransferToMobile']['country'];
        $this->modelTransferToMobile->type            = $_POST['TransferToMobile']['type'];
        $this->modelTransferToMobile->amountValues    = $_POST['TransferToMobile']['amountValues'];
        $this->modelTransferToMobile->amountValuesBDT = $_POST['TransferToMobile']['amountValuesBDT'];
        $this->modelTransferToMobile->meter           = $_POST['TransferToMobile']['meter'];
        $this->modelTransferToMobile->number          = $_POST['TransferToMobile']['number'];

        if (!isset($_POST['TransferToMobile']['confirmed'])) {
            $this->render('confirmPre', array(
                'modelTransferToMobile' => $this->modelTransferToMobile,
            ));
            return;
        }
        $this->confirmRefillPre();

    }

    public function confirmRefillBill()
    {

        $product = $_POST['TransferToMobile']['bill_amount']; //is the amout to refill

        $modelSendCreditRates = SendCreditRates::model()->find(array(
            'condition' => 'id_user = :key AND id_product = :key1',
            'params'    => array(
                ':key'  => Yii::app()->session['id_user'],
                ':key1' => Yii::app()->session['id_product'],
            ),
        ));

        if (!isset($modelSendCreditRates->id)) {

            echo '<div align=center id="container">';
            echo Yii::app()->session['id_user'] . ' ' . $product . ' ' . Yii::app()->session['operatorId'] . "<br>";
            echo '<font color=red>ERROR: 413</font><br><br>';
            echo '<a href="../../index.php/transferToMobile/read">Start new request </a>' . "<br><br>";
            echo '</div>';
            exit;
        }

        $this->sell_price                     = $modelSendCreditRates->sell_price;
        $this->cost                           = $modelSendCreditRates->idProduct->wholesale_price;
        $this->local_currency                 = $modelSendCreditRates->idProduct->currency_dest;
        $this->operator_name                  = $modelSendCreditRates->idProduct->operator_name;
        $this->modelTransferToMobile->product = $product = $modelSendCreditRates->idProduct->product;

        $this->calculateCost($product);

        $this->addInDataBase();

        $result = Orange2::billElectricity($_POST['TransferToMobile'], $modelSendCreditRates, $this->test);

        $this->checkResult($result);

        $this->updateDataBase();
        exit;

    }

    public function confirmRefillPre()
    {

        $product = $_POST['TransferToMobile']['amountValues']; //is the amout to refill

        $modelSendCreditRates = SendCreditRates::model()->find(array(
            'condition' => 'id_user = :key AND id_product = :key1',
            'params'    => array(
                ':key'  => Yii::app()->session['id_user'],
                ':key1' => $product,
            ),
        ));

        if (!isset($modelSendCreditRates->id)) {

            echo '<div align=center id="container">';
            echo Yii::app()->session['id_user'] . ' ' . $product . ' ' . Yii::app()->session['operatorId'] . "<br>";
            echo '<font color=red>ERROR: 413</font><br><br>';
            echo '<a href="../../index.php/transferToMobile/read">Start new request </a>' . "<br><br>";
            echo '</div>';
            exit;
        }

        $this->sell_price                     = $modelSendCreditRates->sell_price;
        $this->cost                           = $modelSendCreditRates->idProduct->wholesale_price;
        $this->local_currency                 = $modelSendCreditRates->idProduct->currency_dest;
        $this->operator_name                  = $modelSendCreditRates->idProduct->operator_name;
        $this->modelTransferToMobile->product = $product = $modelSendCreditRates->idProduct->product;

        $this->calculateCost($product);

        $this->addInDataBase();

        $result = Orange2::sendCredit($_POST['TransferToMobile']['number'], $modelSendCreditRates, $this->test);

        $this->checkResult($result);

        $this->updateDataBase();
        exit;

    }

    public function getAmountBill()
    {

        $modelSendCreditProducts = SendCreditProducts::model()->findAll(
            'country = :key AND status = 1 AND type = "payment" AND product LIKE "%-%" AND operator_name LIKE "Bill%"',
            array(
                ':key' => $this->modelTransferToMobile->country,
            ));

        if (!isset($modelSendCreditProducts[0])) {

            echo '<div align=center id="container">';
            echo "<font color=red>ERROR. No exist product to this number. Contact admin</font><br><br>";
            echo '<a href="../../index.php/transferToMobile/read">Start new request </a>' . "<br><br>";
            echo '</div>';
            exit;
        }

        $ids_products = array();
        foreach ($modelSendCreditProducts as $key => $products) {
            $ids_products[] = $products->id;
        }

        //get the user prices to mount the amount combo
        $criteria = new CDbCriteria();
        $criteria->addInCondition('id_product', $ids_products);
        $criteria->addCondition('id_user = :key');
        $criteria->params[':key'] = Yii::app()->session['id_user'];

        $modelSendCreditRates = SendCreditRates::model()->findAll($criteria);

        if (!count($modelSendCreditRates)) {
            exit('Before send credit, you need add your sell price');
        }

        $i                                 = 0;
        Yii::app()->session['is_interval'] = false;
        foreach ($modelSendCreditProducts as $key => $product) {
            Yii::app()->session['currency_dest'] = $product->currency_dest;
            Yii::app()->session['currency_orig'] = $product->currency_orig;

            Yii::app()->session['is_interval']                 = true;
            Yii::app()->session['interval_currency']           = $product->currency_dest;
            Yii::app()->session['interval_product_id']         = $product->id;
            Yii::app()->session['interval_product_interval']   = $product->product;
            Yii::app()->session['interval_product_sell_price'] = trim($modelSendCreditRates[$i]->sell_price);

            Yii::app()->session['allowedAmount'] = explode('-', $product->product);

            $i++;

        }

        Yii::app()->session['ids_products'] = $ids_products;

        $this->modelTransferToMobile->country  = $modelSendCreditProducts[0]->country;
        $this->modelTransferToMobile->operator = $modelSendCreditProducts[0]->operator_name;

    }

    public function getAmount()
    {
        $modelSendCreditProducts = SendCreditProducts::model()->findAll('country = :key AND status = 1 AND type = "payment"', array(':key' => $this->modelTransferToMobile->country));

        if (!isset($modelSendCreditProducts[0])) {

            echo '<div align=center id="container">';
            echo "<font color=red>ERROR. No exist product to this number. Contact admin</font><br><br>";
            echo '<a href="../../index.php/transferToMobile/read">Start new request </a>' . "<br><br>";
            echo '</div>';
            exit;
        }

        $ids_products = array();
        foreach ($modelSendCreditProducts as $key => $products) {
            $ids_products[] = $products->id;
        }

        //get the user prices to mount the amount combo
        $criteria = new CDbCriteria();
        $criteria->addInCondition('id_product', $ids_products);
        $criteria->addCondition('id_user = :key');
        $criteria->params[':key'] = Yii::app()->session['id_user'];

        $modelSendCreditRates = SendCreditRates::model()->findAll($criteria);

        if (!count($modelSendCreditRates)) {
            exit('Before send credit, you need add your sell price');
        }

        $values                            = array();
        $i                                 = 0;
        Yii::app()->session['is_interval'] = false;
        $values                            = [];
        foreach ($modelSendCreditProducts as $key => $product) {
            Yii::app()->session['currency_dest'] = $product->currency_dest;
            Yii::app()->session['currency_orig'] = $product->currency_orig;
            if (is_numeric($product->product)) {

                if ($this->test == true) {
                    echo $product->id . ' -> ' . $product->currency_dest . ' ' . $product->product . ' = ' . $product->currency_orig . ' ' . trim($modelSendCreditRates[$i]->sell_price) . "<BR>";
                }
                $values[trim($product->id)] = '<font size=1px>' . $product->currency_dest . '</font> ' . trim($product->product) . ' = <font size=1px>' . $product->currency_orig . '</font> ' . trim($modelSendCreditRates[$i]->sell_price);

            } else {
                Yii::app()->session['is_interval']                 = true;
                Yii::app()->session['interval_currency']           = $product->currency_dest;
                Yii::app()->session['interval_product_id']         = $product->id;
                Yii::app()->session['interval_product_interval']   = $product->product;
                Yii::app()->session['interval_product_sell_price'] = trim($modelSendCreditRates[$i]->sell_price);

                Yii::app()->session['allowedAmount'] = explode('-', $product->product);
            }
            $i++;

        }

        Yii::app()->session['amounts']      = $values;
        Yii::app()->session['ids_products'] = $ids_products;

        $this->modelTransferToMobile->country  = $modelSendCreditProducts[0]->country;
        $this->modelTransferToMobile->operator = $modelSendCreditProducts[0]->operator_name;

    }

    public function addInDataBase()
    {
        $modelSendCreditSummary            = new SendCreditSummary();
        $modelSendCreditSummary->id_user   = Yii::app()->session['id_user'];
        $modelSendCreditSummary->service   = 'Payment';
        $modelSendCreditSummary->number    = $this->modelTransferToMobile->number;
        $modelSendCreditSummary->confirmed = 0;
        $modelSendCreditSummary->cost      = $this->user_cost;
        $modelSendCreditSummary->save();
        $this->send_credit_id = $modelSendCreditSummary->id;
    }

    public function updateDataBase()
    {

        if ($this->sell_price > 0 && $this->user_cost > 0) {

            $profit = 'transfer_international_profit';
            SendCreditSummary::model()->updateByPk($this->send_credit_id, array(
                'profit' => $this->modelTransferToMobile->{$profit},
                'amount' => $this->cost,
                'sell'   => number_format($this->sell_price, 2),
                'earned' => number_format($this->sell_price - $this->user_cost, 2),
            ));
        } else {
            SendCreditSummary::model()->deleteByPk($this->send_credit_id);
        }
    }

    public function calculateCost($product = 0)
    {

        $methosProfit = 'transfer_international_profit';

        if ($this->test == true) {
            echo "<br>" . 'cost=' . $this->cost . ' - prodict=' . $product . "<br>";
        }

        if ($this->modelTransferToMobile->credit + $this->modelTransferToMobile->creditlimit < $this->cost) {

            echo '<div id="container"><div id="form"> <div id="box-4"><div class="control-group">';
            echo '<form action="" id="form1" method="POST">';
            echo '<font color=red>ERROR:You no have enough credit to transfer</font>';
            echo '</form>';
            echo '</div></div></div></div>';
            exit;
        }

        $user_profit = $this->modelTransferToMobile->{$methosProfit};

        $this->user_cost = $this->cost - ($this->cost * ($user_profit / 100));

        $this->user_profit = $this->cost * ($user_profit / 100);

        if ($this->test == true) {
            echo 'cost=' . $this->cost . ', user_profit= ' . $this->cost * ($this->user_profit / 100) . ' user_profit=' . $this->user_profit . "<BR>";
        }

        if ($this->modelTransferToMobile->id_user > 1) {

            //check if agent have credit

            $modelAgent = User::model()->findByPk($this->modelTransferToMobile->id_user);

            if ($modelAgent->credit + $modelAgent->creditlimit < $this->cost) {

                echo '<div id="container"><div id="form"> <div id="box-4"><div class="control-group">';
                echo '<form action="" id="form1" method="POST">';
                echo '<font color=red>ERROR:Your Agent no have enough credit to transfer</font>';
                echo '</form>';
                echo '</div></div></div></div>';
                exit;
            }

            $agentProfit = $modelAgent->{$methosProfit};

            $this->agent_cost = $this->cost - ($this->cost * ($agentProfit / 100));

            if ($this->test == true) {
                echo 'agentProfit=' . $agentProfit . ' | $this->agent_cost=' . $this->agent_cost . "<BR>";
            }
        }
    }

    public function actionGetBuyingPrice()
    {

        $currency = $this->config['global']['BDService_cambio'];

        $rateinitial = $this->modelTransferToMobile->transfer_bdservice_rate / 100 + 1;
        //cost to send to provider selected value + admin rate * exchange
        $cost     = $_GET['amountValues'] * $rateinitial * $this->config['global']['BDService_cambio'];
        $product  = 0;
        $currency = '€';

        $methosProfit = 'transfer_' . $_GET['method'] . '_profit';
        $user_profit  = $this->modelTransferToMobile->{$methosProfit};

        $user_cost = $cost - ($cost * ($user_profit / 100));
        echo $currency . ' ' . number_format($user_cost, 2);

    }

    public function checkResult($result)
    {

        if (preg_match("/successful/", $result)) {
            $this->releaseCredit($result, '');
        } else if (preg_match("/ERROR|error/", $result)) {
            echo '<div align=center id="container">';
            echo "<font color=red>" . $result . "</font><br><br>";
            echo '<a href="../../index.php/transferToMobile/read">Start new request </a>' . "<br><br>";
            echo '</div>';
            exit;
        }

    }

    public function releaseCredit($result, $status)
    {

        $result = explode("=", $result);

        if ($this->test == true) {
            echo 'cost=' . $this->cost . '. user_cost=' . $this->user_cost . "<br>";
            echo $this->modelTransferToMobile->transfer_show_selling_price . "<br>";
        }

        $argument = $this->modelTransferToMobile->transfer_show_selling_price;
        if ($argument < 10) {
            $fee = '1.0' . $argument;
        } else {
            $fee = '1.' . $argument;
        }

        if ($this->test == true) {
            echo $fee . "<br>";
            echo $this->modelTransferToMobile->transfer_show_selling_price;
        }

        $this->showprice = number_format($this->cost * $fee, 2);

        if ($status == 'error') {
            $description = 'PENDING: ';
        } else {
            $description = '';
        }
        //Send Credit BDT 150 to 01630593593 via flexiload at 2.25"
        $description .= 'Send Credit BDT ' . $this->modelTransferToMobile->amountValuesBDT . ' - ' . $this->modelTransferToMobile->number . ' via ' . $this->modelTransferToMobile->method . ' - EUR ' . $this->sell_price;

        if ($this->test == true) {
            echo $description;
        }

        $payment = 0;
        $values  = ":id_user, :costUser, :description, $payment, $this->send_credit_id";
        $field   = 'id_user,credit,description,payment,invoice_number';

        $sql     = "INSERT INTO pkg_refill ($field) VALUES ($values)";
        $command = Yii::app()->db->createCommand($sql);
        $command->bindValue(":id_user", Yii::app()->session['id_user'], PDO::PARAM_INT);
        $command->bindValue(":costUser", $this->user_cost * -1, PDO::PARAM_STR);
        $command->bindValue(":description", $description, PDO::PARAM_STR);
        $command->execute();

        $msg = $result[1] . "<br>TransactionID " . $result[3];

        echo '<div align=center id="container">';
        echo '<font color=green>Success: ' . $msg . '</font>' . "<br><br>";
        echo '<a href="../../index.php/transferToMobile/read">Start new request </a>' . "<br><br>";
        echo '<a href="../../index.php/transferToMobile/printRefill?id=' . Yii::app()->db->lastInsertID . '">Print Refill </a>' . "<br><br>";
        echo '</div>';

        if ($this->test == true) {
            echo $sql . "<br>";
        }

        if ($this->modelTransferToMobile->id_user > 1) {

            $payment = 0;
            $values  = ":id_user, :costAgent, :description, $payment";
            $field   = 'id_user,credit,description,payment';

            $values .= ",$this->send_credit_id";
            $field .= ',invoice_number';

            if ($this->test == true) {
                echo 'UPDATE AGENT CREDIT -> ' . $sql . "<br>";
            }

            $sql     = "INSERT INTO pkg_refill ($field) VALUES ($values)";
            $command = Yii::app()->db->createCommand($sql);
            $command->bindValue(":id_user", $this->modelTransferToMobile->id_user, PDO::PARAM_INT);
            $command->bindValue(":costAgent", $this->agent_cost * -1, PDO::PARAM_STR);
            $command->bindValue(":description", $description, PDO::PARAM_STR);
            $command->execute();

            if ($this->test == true) {
                echo 'INSERT AGENT REFILL -> ' . $sql . "<br>";
            }

        }
    }

    public function actionPrintRefill()
    {

        if (isset($_GET['id'])) {
            echo '<center>';
            $config    = LoadConfig::getConfig();
            $id_refill = $_GET['id'];

            $modelRefill = Refill::model()->findByPk((int) $id_refill, 'id_user = :key', array(':key' => Yii::app()->session['id_user']));

            echo $config['global']['fm_transfer_print_header'] . "<br><br>";

            echo $modelRefill->idUser->company_name . "<br>";
            echo $modelRefill->idUser->address . ', ' . $modelRefill->idUser->city . "<br>";
            echo "Trx ID: " . $modelRefill->id . "<br>";

            echo $modelRefill->date . "<br>";

            $number = explode(" ", $modelRefill->description);

            echo "Cellulare.: " . $number[5] . "<br>";

            if (preg_match('/Meter/', $modelRefill->description)) {
                $tmp = explode('Meter', $modelRefill->description);
                echo 'Meter: ' . $tmp[1] . "<br>";
            }

            $tmp    = explode('EUR ', $modelRefill->description);
            $tmp    = explode('. T', $tmp[1]);
            $amount = $tmp[0];

            $tmp      = explode('via ', $modelRefill->description);
            $operator = strtok($tmp[1], '-');
            $tmp      = explode('Send Credit ', $modelRefill->description);
            $tmp      = explode(' -', $tmp[1]);
            $product  = $tmp[0];

            echo 'Prodotto:  ' . $product . ' ' . $operator . "<br>";

            echo "Importo: EUR <input type=text' style='text-align: right;' size='6' value='$amount'> <br><br>";

            echo $config['global']['fm_transfer_print_footer'] . "<br><br>";

            echo '<td><a href="javascript:window.print()">Print</a></td>';
            echo '</center>';
        } else {
            echo ' Invalid reffil';
        }
    }

    public function actionConvertCurrency()
    {
        $method = 'Payment';

        $country = $_GET['country'];

        $operator_name = isset($_GET['type']) ? $_GET['type'] : 'Prepaid';

        $modelSendCreditProducts = SendCreditProducts::model()->findAll('country = :key AND status = 1 AND type = "payment" AND product LIKE "%-%" AND operator_name LIKE :key1',
            array(
                ':key'  => $country,
                ':key1' => $operator_name . '%',
            ));

        if ($_GET['currency'] == 'EUR') {

            /*
            Request 2: to Send EUR 2.00, will show Selling price EUR 2.00 and BDT amount converted to BDT 125 to
            send(2.00-0.75/0.01).
            If click on "R", will show EUR 1.25.
             */

            $amountEUR = $_GET['amount'];

            $amountBDT = $amountEUR / ($modelSendCreditProducts[0]->wholesale_price);

            foreach ($modelSendCreditProducts as $key => $value) {

                $product = explode('-', $value->product);
                if ($amountBDT >= $product[0] && $amountBDT <= $product[1]) {
                    $product = $value;
                    break;
                }
            }

            if (!isset($product->product)) {
                exit('invalid');
            }

            $modelSendCreditRates = SendCreditRates::model()->find(array(
                'condition' => 'id_user = :key AND id_product = :key1',
                'params'    => array(
                    ':key'  => Yii::app()->session['id_user'],
                    ':key1' => $product->id,
                ),
            ));

            echo $amount = number_format(($amountEUR - $modelSendCreditRates->sell_price) / $modelSendCreditProducts[0]->wholesale_price, 0, '', '');
        } else {

            /*
            Request 1: to Send BDT 150, will show Selling price EUR 2.25(150*0.01+0.75). If click on "R", will show EUR 1.5.
             */
            $amountBDT = $_GET['amount'];

            foreach ($modelSendCreditProducts as $key => $value) {

                $product = explode('-', $value->product);
                if ($amountBDT >= $product[0] && $amountBDT <= $product[1]) {
                    $product = $value;
                    break;
                }
            }

            if (!isset($product->product)) {
                exit('invalid');
            }

            $modelSendCreditRates = SendCreditRates::model()->find(array(
                'condition' => 'id_user = :key AND id_product = :key1',
                'params'    => array(
                    ':key'  => Yii::app()->session['id_user'],
                    ':key1' => $product->id,
                ),
            ));

            echo $amount                      = number_format(($amountBDT * $product->wholesale_price) + $modelSendCreditRates->sell_price, 2);
            Yii::app()->session['sell_price'] = $amount;
            Yii::app()->session['id_product'] = $product->id;
        }
    }

    public function actionCheckNumber($value = '')
    {

        $metric_operator_name                       = Orange2::checkMetric($_GET['meter'], $_GET['country']);
        Yii::app()->session['metric_operator_name'] = $metric_operator_name;
        if ($metric_operator_name === false) {
            echo 'The Meter number is invalid. Please try again';

            exit;
        } else {
            echo $metric_operator_name;
        }
    }

}
