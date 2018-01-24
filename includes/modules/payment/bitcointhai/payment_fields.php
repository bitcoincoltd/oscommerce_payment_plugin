<?php /** @var PaymentDetailsResponse $payment_details */ ?>
<p>
<?php echo MODULE_PAYMENT_BITCOINTHAI_TEXT_PAYMSG; ?>
</p>
<div class="payment">
<ul>
<?php
$i = 1;
$actual_link = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
foreach($payment_details as $key => $value) {
  foreach($value as $key => $item){
    if( $item->available == true ) {
      echo "<li>";
      echo "<a href='".$actual_link."#item-".$i."'>".$item->name." (" . $key .")</a>";
      echo "<div class='panel' id='item-".$i."'>";
      echo "<p>".$key." address: ";
      echo "<a href=".$item->payment_url."><span class='address'>".$item->address."</span></a>";
      echo "</span></p>";
      echo "<p>Amount: ".$item->amount."</p>";
      echo "<p>";
      echo "<a href=".$item->payment_url.">";
      echo "<img src='data:image/png;base64,".$item->qr_code_base64."' alt='Send to ".$item->address."' style='width:200px;height:200px;max-height:200px;'>";
      echo "</a>";
      echo "</p>";
      echo "</div>";
      echo "</li>";
      $i++;
    }
  }
}
 ?>
</ul>
  <p class="marked">
    <strong>
     <?= MODULE_PAYMENT_BITCOINTHAI_TEXT_AFTERPAY; ?>
    </strong>
  </p>
</div>
<!-- End of payment -->

<style>
.payment ul {list-style:none;margin:0;padding:0}
.payment ul li a {
  display: block;
  text-decoration:none;
  background-color: #eee;
  color: #444;
  cursor: pointer;
  padding: 12px;
  width: 100%;
  border: none;
  text-align: left;
  outline: none;
  font-size: 15px;
  transition: 0.4s;
  margin-top: 5px;
}
.payment li .panel p img {float: none !important; margin:0 auto;}
.payment .panel {display: none;}
.payment .panel:target{display:block;border:1px solid #ddd; padding:5px;}
.payment .panel .address { font-size: 0.8em;color: #222; }

.payment .marked {
  border: #4f9135 solid 1px;
  background-color: #b5eeb8;
  padding: 10px;
  margin-top: 10px !important;
}
</style>
