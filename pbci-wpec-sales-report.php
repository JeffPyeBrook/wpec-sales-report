<?php
/*
 Plugin Name: PBCI WPEC Sales Reporter
 Plugin URI:
 Description: Display and export wp-e-commerce sales report and export
 Version: 1.0
 Author: PBCI / Jeffrey Schutzman
 Author URI:
*/


class pbci_wpec_iif_exporter {

	/**
	 *
	 *
	 * @return unknown
	 */
	function pbci_wpec_iif_exporter() {

		add_action ( 'admin_menu', array ( &$this, 'admin_init' ), 20 );

		return true;
	}

	function admin_init() {
		$menupage = add_menu_page('WPEC Sales', 'WPEC Sales', 'manage_options', 'pbci_wpec_sales_report', array(&$this,'sales_report'),plugin_dir_url( __FILE__ ).'pye-brook-logo-16-16.png' );

		add_submenu_page( 'pbci_wpec_sales_report', 'Sales Report', 'Report', 'administrator', 'sales-report', array( $this, 'sales_report' ) );
		add_submenu_page( 'pbci_wpec_sales_report', 'Export Sales', 'Export', 'administrator', 'export-select', array( $this, 'export_select' ) );
		add_submenu_page( 'pbci_wpec_sales_report', 'Export Settings', 'Settings', 'administrator', 'export-config', array( $this, 'export_config' ) );
	}

	function do_sales_report() {
		global $wpdb;

		$trnsid = 1;
		$gateway_accounts = get_option( 'pbci_gateway_accounts', array() );
		$export_accounts = get_option( 'pbci_export_accounts', array('sales_revenue' => 'Product Revenue', 'shipping' => 'Shipping', 'sales_tax_account' => 'Sales Tax Payable', 'sales_tax_payee' => 'Sales Tax' ) );

		$periods = array_keys( $_POST['period'] );

		$period_count = 0;
		$grand_total_items                         = 0;
		$grand_total_item_taxable                  = 0;
		$grand_total_item_non_taxable              = 0;
		$grand_total_discounts                     = 0;
		$grand_total_discount_taxable              = 0;
		$grand_total_discount_non_taxable          = 0;
		$grand_total_shipping_transasctions        = 0;
		$grand_total_shipping_taxable              = 0;
		$grand_total_shipping_non_taxable          = 0;
		$grand_total_tax_transactions              = 0;
		$grand_total_tax_taxable                   = 0;
		$grand_total_tax_non_taxable               = 0;
		$grand_total_transactions                  = 0;
		$grand_total_transaction_total_taxable     = 0;
		$grand_total_transaction_total_non_taxable = 0;

		ob_start();
		foreach ( $periods as $period ) {
			$a = explode( '-', $period );
			$year = $a[0];
			$month = $a[1];
			$period_count++;

			$sql = "SELECT ID FROM " . WPSC_TABLE_PURCHASE_LOGS . ' WHERE MONTH( FROM_UNIXTIME( date ) ) = ' . $month . ' AND YEAR( FROM_UNIXTIME( DATE ) ) = '. $year . ' ORDER by date DESC';
			$result = $wpdb->get_col( $sql, 0 );
			$purchase_log_ids = array_map( 'intval', $result );

			$datestring =  date("F Y", mktime(0, 0, 0, $month, 1, $year));

			$items                         = 0;
			$item_taxable                  = 0;
			$item_non_taxable              = 0;
			$discounts                     = 0;
			$discount_taxable              = 0;
			$discount_non_taxable          = 0;
			$shipping_transasctions        = 0;
			$shipping_taxable              = 0;
			$shipping_non_taxable          = 0;
			$tax_transactions              = 0;
			$tax_taxable                   = 0;
			$tax_non_taxable               = 0;
			$transactions                  = 0;
			$transaction_total_taxable     = 0;
			$transaction_total_non_taxable = 0;
			$taxable_transactions          = 0;
			$this_transaction_item_total   = 0;
			$taxable_total                 = 0;
			$not_taxable_transactions      = 0;
			$not_taxable_total             = 0;


			$max_rows = 1;
			foreach ( $purchase_log_ids as $purchase_log_id ) {
				$purchase_log = new WPSC_Purchase_Log( $purchase_log_id );

				$gateway_id = $purchase_log->get( 'gateway' );
				$data = $purchase_log->get_data();

//				if ( empty( $gateway_accounts[$gateway_id] ) ) {
//					continue;
//				}

				if ( $purchase_log->is_incomplete_sale() ) {
					continue;
				}

				if ( $purchase_log->is_payment_declined() ) {
					continue;
				}

				if ( $purchase_log->is_refunded() ) {
					continue;
				}

				if ( $purchase_log->is_refund_pending() ) {
					continue;
				}

//				if ( ($purchase_log->get('processed') != WPSC_Purchase_Log::ACCEPTED_PAYMENT) && ($purchase_log->get('processed') != WPSC_Purchase_Log::CLOSED_ORDER) ) {
//					continue;
//				}

				$checkout_form_data = new WPSC_Checkout_Form_Data ( $purchase_log_id );
				$checkout = $checkout_form_data->get_data();

				$timestamp = $purchase_log->get( 'date' );
				$thedate = date( 'm/d/Y' , $timestamp );

				$transactions++;

				$t = floatval( $purchase_log->get( 'wpec_taxes_total' ) );
				if ( $t > 0 ) {
					$is_taxable = true;
					$taxable_transactions++;
					$taxable_total += ($this_transaction_item_total - $d);
				} else {
					$is_taxable = false;
					$not_taxable_transactions++;
					$not_taxable_total += ($this_transaction_item_total - $d);
				}

				if ( $is_taxable ) {
					$transaction_total_taxable += $purchase_log->get( 'totalprice' );
				} else {
					$transaction_total_non_taxable += $purchase_log->get( 'totalprice' );
				}


				$t = floatval( $purchase_log->get( 'wpec_taxes_total' ) );
				if ( $is_taxable ) {
					$tax_transactions++;
					if ( $is_taxable )
						$tax_taxable += $t;
					else
						$tax_non_taxable += $t;
				}

				$cart_contents = $purchase_log->get_cart_contents();

				foreach ( $cart_contents as $cart_item ) {
					$items = $items +  $cart_item->quantity;
					$this_transaction_item_total = ($cart_item->price *  $cart_item->quantity);
					if ( $is_taxable )
						$item_taxable += $this_transaction_item_total;
					else
						$item_non_taxable += $this_transaction_item_total;
				}

				$s = floatval ( $purchase_log->get( 'total_shipping' ) );
				if ( $s != 0 ) {
					$shipping_transasctions++;
					if ( $is_taxable )
						$shipping_taxable += $s;
					else
						$shipping_non_taxable += $s;
				}

				$d = floatval( $purchase_log->get( 'discount_value' ) );
				if ( $d > 0 ) {
					$discounts++;
					if ( $is_taxable )
						$discount_taxable += $d;
					else
						$discount_non_taxable += $d;
				}


				$t = floatval( $purchase_log->get( 'wpec_taxes_total' ) );
				if ( $t > 0 ) {
					$taxable_transactions++;
					$taxable_total += ($this_transaction_item_total - $d);
				} else {
					$not_taxable_transactions++;
					$not_taxable_total += ($this_transaction_item_total - $d);
				}


			}

			$grand_total_items 						   += $items;
			$grand_total_item_taxable                  += $item_taxable;
			$grand_total_item_non_taxable              += $item_non_taxable;
			$grand_total_discounts                     += $discounts;
			$grand_total_discount_taxable              += $discount_taxable;
			$grand_total_discount_non_taxable          += $discount_non_taxable;
			$grand_total_shipping_transasctions        += $shipping_transasctions;
			$grand_total_shipping_taxable              += $shipping_taxable;
			$grand_total_shipping_non_taxable          += $shipping_non_taxable;
			$grand_total_tax_transactions              += $tax_transactions;
			$grand_total_tax_taxable                   += $tax_taxable;
			$grand_total_tax_non_taxable               += $tax_non_taxable;
			$grand_total_transactions                  += $transactions;
			$grand_total_transaction_total_taxable     += $transaction_total_taxable;
			$grand_total_transaction_total_non_taxable += $transaction_total_non_taxable;



			?>

			<table class="rounded-corner">
				<tr>
					<th>
						<?php echo $datestring;?>
					</th>
					<th>
						#
					</th>

					<th>
						taxable
					</th>

					<th>
						non-taxable
					</th>

					<th>
						total
					</th>
				</tr>


				<tr>
					<td>
						items
					</td>
					<td>
						<?php echo $items;?>
					</td>

					<td>
						$<?php echo number_format($item_taxable,2);?>
					</td>

					<td>
						$<?php echo number_format($item_non_taxable,2);?>
					</td>

					<td>
						$<?php echo number_format($item_taxable+$item_non_taxable,2);?>
					</td>
				</tr>

				<tr>
					<td>
						discounts
					</td>
					<td>
						<?php echo $discounts;?>
					</td>

					<td>
						$<?php echo number_format($discount_taxable,2);?>
					</td>

					<td>
						$<?php echo number_format($discount_non_taxable,2);?>
					</td>

					<td>
						$<?php echo number_format($discount_non_taxable+$discount_taxable,2);?>
					</td>
				</tr>


				<tr>
					<td>
						net sales
					</td>
					<td>
						<?php echo $items;?>
					</td>

					<td>
						$<?php echo number_format($item_taxable-$discount_taxable,2);?>
					</td>

					<td>
						$<?php echo number_format($item_non_taxable-$discount_non_taxable,2);?>
					</td>

					<td>
						$<?php echo number_format( ($item_taxable+$item_non_taxable) - ($discount_non_taxable+$discount_taxable) ,2);?>
					</td>
				</tr>

				<tr>
					<td>
						tax collected
					</td>
					<td>
						<?php echo $tax_transactions;?>
					</td>

					<td>
						$<?php echo number_format($tax_taxable,2);?>
					</td>

					<td>
						$<?php echo number_format($tax_non_taxable,2);?>
					</td>

					<td>
						$<?php echo number_format($tax_non_taxable+$tax_taxable,2);?>
					</td>
				</tr>

				<tr>
					<td>
						shipping
					</td>
					<td>
						<?php echo $shipping_transasctions;?>
					</td>

					<td>
						$<?php echo number_format($shipping_taxable,2);?>
					</td>

					<td>
						$<?php echo number_format($shipping_non_taxable,2);?>
					</td>

					<td>
						$<?php echo number_format($shipping_non_taxable+$shipping_taxable,2);?>
					</td>
				</tr>

				<tr>
					<td>
						transactions
					</td>
					<td>
						<?php echo $transactions;?>
					</td>
					<td>
						$<?php echo number_format($transaction_total_taxable,2);?>
					</td>
					<td>
						$<?php echo number_format($transaction_total_non_taxable,2);?>
					</td>
					<td>
						$<?php echo number_format($transaction_total_non_taxable+$transaction_total_taxable,2);?>
					</td>

				</tr>


			</table>
			<br>
			<?php
			if ( $period_count > 0 ) {
			?>
			<style>
			.rounded-corner {
					font-family: "Lucida Sans Unicode", "Lucida Grande", Sans-Serif;
					font-size: 12px;
					width: 480px;
					text-align: left;
					border-collapse: collapse;
					margin: 20px;
					}
			.rounded-corner th {
					font-weight: normal;
					font-size: 13px;
					color: #039;
					background: #b9c9fe;
					padding: 8px;
					}
			.rounded-corner td {
					background: #e8edff;
					border-top: 1px solid #fff;
					color: #669;
					padding: 8px;
					}
			</style>
			<?php
			}
		}

		$details = ob_get_clean();

		if ( $period_count > 1 ) {
			?>
			<table class="rounded-corner">
				<tr>
					<th>
						Summary
					</th>
					<th>
						#
					</th>

					<th>
						taxable
					</th>

					<th>
						non-taxable
					</th>

					<th>
						total
					</th>
				</tr>


				<tr>
					<td>
						items
					</td>
					<td>
						<?php echo $grand_total_items;?>
					</td>

					<td>
						$<?php echo number_format($grand_total_item_taxable,2);?>
					</td>

					<td>
						$<?php echo number_format($grand_total_item_non_taxable,2);?>
					</td>

					<td>
						$<?php echo number_format($grand_total_item_taxable+$grand_total_item_non_taxable,2);?>
					</td>
				</tr>

				<tr>
					<td>
						discounts
					</td>
					<td>
						<?php echo $grand_total_discounts;?>
					</td>

					<td>
						$<?php echo number_format($grand_total_discount_taxable,2);?>
					</td>

					<td>
						$<?php echo number_format($grand_total_discount_non_taxable,2);?>
					</td>

					<td>
						$<?php echo number_format($grand_total_discount_non_taxable+$grand_total_discount_taxable,2);?>
					</td>
				</tr>


				<tr>
					<td>
						net sales
					</td>
					<td>
						<?php echo $grand_total_items;?>
					</td>

					<td>
						$<?php echo number_format($grand_total_item_taxable-$grand_total_discount_taxable,2);?>
					</td>

					<td>
						$<?php echo number_format($grand_total_item_non_taxable-$grand_total_discount_non_taxable,2);?>
					</td>

					<td>
						$<?php echo number_format( ($grand_total_item_taxable+$grand_total_item_non_taxable) - ($grand_total_discount_non_taxable+$grand_total_discount_taxable) ,2);?>
					</td>
				</tr>

				<tr>
					<td>
						tax collected
					</td>
					<td>
						<?php echo $grand_total_tax_transactions;?>
					</td>

					<td>
						$<?php echo number_format($grand_total_tax_taxable,2);?>
					</td>

					<td>
						$<?php echo number_format($grand_total_tax_non_taxable,2);?>
					</td>

					<td>
						$<?php echo number_format($grand_total_tax_non_taxable+$grand_total_tax_taxable,2);?>
					</td>
				</tr>

				<tr>
					<td>
						shipping
					</td>
					<td>
						<?php echo $grand_total_shipping_transasctions;?>
					</td>

					<td>
						$<?php echo number_format($grand_total_shipping_taxable,2);?>
					</td>

					<td>
						$<?php echo number_format($grand_total_shipping_non_taxable,2);?>
					</td>

					<td>
						$<?php echo number_format($grand_total_shipping_non_taxable+$grand_total_shipping_taxable,2);?>
					</td>
				</tr>

				<tr>
					<td>
						transactions
					</td>
					<td>
						<?php echo $grand_total_transactions;?>
					</td>
					<td>
						$<?php echo number_format($grand_total_transaction_total_taxable,2);?>
					</td>
					<td>
						$<?php echo number_format($grand_total_transaction_total_non_taxable,2);?>
					</td>
					<td>
						$<?php echo number_format($grand_total_transaction_total_non_taxable+$grand_total_transaction_total_taxable,2);?>
					</td>
				</tr>

			</table>
			<br>
			<?php
		}

		echo $details;
	}

	function sales_report() {

		if ( isset( $_REQUEST['period'] ) ) {
			update_option( 'pbci_sales_report_periods', $_REQUEST['period'] );
		}

		?>
		<h2>Sales Report</h2>
		<hr>
		<?php
		global $wpdb;
		?>
			<form method="post">
			<?php
			$periods = isset( $_REQUEST['period'] ) ? $_REQUEST['period'] : get_option( 'pbci_sales_report_periods', array() );
			$previous_year = '';

			$sql = "SELECT DISTINCT DATE_FORMAT(from_unixtime(date), '%M %Y') as label , MONTH( FROM_UNIXTIME( DATE ) ) as m , YEAR( FROM_UNIXTIME( DATE ) ) as y FROM " . WPSC_TABLE_PURCHASE_LOGS . ' ORDER by date DESC';
			$months = $wpdb->get_results( $sql, 0 );
			foreach ( $months as $month ) {
				if ( $previous_year != $month->y ) {
					echo '<h3>'.$month->y.'</h3>';
					$previous_year = $month->y;
				}

				$checked = isset( $periods[ $month->y.'-'.$month->m ] ) ? 'checked="checked"':'';
				?>
					<input type="checkbox" name="period[<?php echo $month->y.'-'.$month->m;?>]" size="60" value="true" <?php echo $checked;?>> <?php echo $month->label;?>&nbsp;
				<?php
			}
			?>
			<br><br>
			<input type="submit" name="pbci_action" id="pbci_action" class="button-primary" value="Report" />
			</form>

			<?php
			if ( isset( $_REQUEST['pbci_action'] ) && ($_REQUEST['pbci_action'] == 'Report') && !empty($_REQUEST['period']) ) {
				?><hr><?php
				$this->do_sales_report();
				?><hr><?php
			}

		}

	static function export_config() {
		global $wpdb;

		if ( isset( $_REQUEST['pbci_action'] ) && ($_REQUEST['pbci_action'] == 'Save') && !empty($_REQUEST['gateway_account']) ) {
			update_option( 'pbci_gateway_accounts', $_POST['gateway_account'] );
			update_option( 'pbci_export_accounts', $_POST['export_accounts']);
		}

		$gateway_accounts = get_option( 'pbci_gateway_accounts', array() );
		$export_accounts = get_option( 'pbci_export_accounts', array('sales_revenue' => 'Product Revenue', 'shipping' => 'Shipping', 'sales_tax_account' => 'Sales Tax Payable', 'sales_tax_payee' => 'Sales Tax' ) );		?>
		<h2>IIF Export Configuration</h2>
		<form method="post">
			<?php

			$sql = 'SELECT distinct gateway FROM ' . WPSC_TABLE_PURCHASE_LOGS . ' ORDER BY `gateway` ASC';
			$gateways = $wpdb->get_col( $sql, 0 );

			?>
			<table>
				<tr>
					<th>
						Gateway ID
					</th>
					<th>
						Quickbooks Account
					</th>
				</tr>

				<?php
				foreach ( $gateways as $gateway ) {
					$account = isset( $gateway_accounts[$gateway] ) ? $gateway_accounts[$gateway] : '';
					?>
					<tr>
						<td>
							<?php echo $gateway;?>
						</td>

						<td>
							<input name="gateway_account[<?php echo $gateway;?>]" size="60" value="<?php echo $account;?>">
						</td>
					</tr>
					<?php
				}
				?>
				<tr>
					<td colspan="2">
						<hr>
					</td>
				</tr>


				<tr>
					<th>
						Split Information
					</th>
					<th>

					</th>
				</tr>

				<tr>
					<td>
						Sales Revenue
					</td>

					<td>
						<input name="export_accounts[sales_revenue]" size="60" value="<?php echo $export_accounts['sales_revenue'];?>">
					</td>
				</tr>

				<tr>
					<td>
						Shipping Account
					</td>

					<td>
						<input name="export_accounts[shipping]" size="60" value="<?php echo $export_accounts['shipping'];?>">
					</td>
				</tr>

				<tr>
					<td>
						Sales Tax Account
					</td>

					<td>
						<input name="export_accounts[sales_tax_account]" size="60" value="<?php echo $export_accounts['sales_tax_account'];?>">
					</td>
				</tr>

				<tr>
					<td>
						Sales Tax Payee
					</td>

					<td>
						<input name="export_accounts[sales_tax_payee]" size="60" value="<?php echo $export_accounts['sales_tax_payee'];?>">
					</td>
				</tr>


			</table>

			<input type="submit" name="pbci_action" id="pbci_action" class="button-primary" value="Save" />
		</form>
		<?php

	}

	function export_select() {

		if ( isset( $_REQUEST['period'] ) ) {
			update_option( 'pbci_sales_export_periods', $_REQUEST['period'] );
		}

		global $wpdb;
		?>
		<h2>IIF Export</h2>
		<hr>
		<form method="post">
		<?php
		$periods = isset( $_REQUEST['period'] ) ? $_REQUEST['period'] : get_option( 'pbci_sales_export_periods', array() );
		$previous_year = '';
		$sql = "SELECT DISTINCT DATE_FORMAT(from_unixtime(date), '%M %Y') as label , MONTH( FROM_UNIXTIME( DATE ) ) as m , YEAR( FROM_UNIXTIME( DATE ) ) as y FROM " . WPSC_TABLE_PURCHASE_LOGS . ' ORDER by date DESC';
		$months = $wpdb->get_results( $sql, 0 );
		foreach ( $months as $month ) {

			$checked = isset( $periods[ $month->y.'-'.$month->m ] ) ? 'checked="checked"':'';
			if ( $previous_year != $month->y ) {
				echo '<h3>'.$month->y.'</h3>';
				$previous_year = $month->y;
			}

			?>
				<input type="checkbox" name="period[<?php echo $month->y.'-'.$month->m;?>]" size="60" value="true" <?php echo $checked;?>> <?php echo $month->label;?>&nbsp;
			<?php
		}
		?>
		<br>
		<br>
		<input type="submit" name="pbci_action" id="pbci_action" class="button-primary" value="Export" />
		</form>
		<?php
	}




	function do_export_file() {
		global $wpdb;

		$trnsid = 1;
		$gateway_accounts = get_option( 'pbci_gateway_accounts', array() );
		$export_accounts = get_option( 'pbci_export_accounts', array('sales_revenue' => 'Product Revenue', 'shipping' => 'Shipping', 'sales_tax_account' => 'Sales Tax Payable', 'sales_tax_payee' => 'Sales Tax' ) );


		$cust = array (
				'NAME'      => '',
				'FIRSTNAME' => '',
				'LASTNAME'  => '',
				'EMAIL'     => '',
				'PHONE1'    => '',
				'BADDR1'    => '',
				'BADDR2'    => '',
				'BADDR3'    => '',
				'BADDR4'    => '',
				'SADDR1'    => '',
				'SADDR2'    => '',
				'SADDR3'    => '',
				'SADDR4'    => '',
		);

		$trans = array (
				'TRNSID'    => '',
				'TRNSTYPE'  => '',
				'DATE'      => '',
				'ACCNT'		=> '',
				'AMOUNT'    => '',
				'NAME'      => '',
				'MEMO'      => '',
				'PAYMETH'   => '',
//				'SHIPVIA'   => '',
//				'TIMESTAMP' => '',
//				'DOCNUM'    => '',
//				'CLEAR'     => '',
//				'SHIPDATE'  => '',
				/*
				'ADDR1'     => '',
				'ADDR2'     => '',
				'ADDR3'     => '',
				'ADDR4'     => '',
				'SADDR1'    => '',
				'SADDR2'    => '',
				'SADDR3'    => '',
				'SADDR4'    => '',
				'INVTITLE'  => '',
				*/
		);

		$spl = array (
				'SPLID'         => '',
				'TRNSTYPE'      => '',
				'DATE'          => '',
				'ACCNT'         => '',
				'AMOUNT'        => '',
				'NAME'          => '',
				'MEMO'          => '',
				'INVITEM'       => '',
				'PRICE'         => '',
				'EXTRA'         => '',
		);

		$content = "";
		$cust_content = "";
		$headers = "";
		$headers .= "!CUST\tNAME\t\n";
		$headers .= "!ACCNT\tNAME\tACCNTTYPE\tEXTRA\tACCNUM\n";

		$content .= "ACCNT\t" . $export_accounts['sales_revenue'] . "\tINC\t\n";
		$content .= "ACCNT\t" . $export_accounts['shipping'] . "\tINC\t\n";
		$content .= "ACCNT\t" . $export_accounts['sales_tax_account'] . "\tOCLIAB\tSALESTAX\t2201\n";

		foreach( $gateway_accounts as $gateway => $account_name ) {
			if ( !empty( $account_name ) ) {
				$content .= "ACCNT\t" . $account_name . "\tBANK\t\n";
			}
		}

		// !TRNS line
		$headers .= '!TRNS';
		foreach ( $trans as $key => $value ) {
			$headers .= "\t" . $key;
		}
		$headers .= "\n";

		// !SPL line
		$headers .= '!SPL';
		foreach ( $spl as $key => $value ) {
			$headers .= "\t" . $key;
		}
		$headers .= "\n";

		// !CUST line
		$headers .= '!CUST';
		foreach ( $cust as $key => $value ) {
			$headers .= "\t" . $key;
		}
		$headers .= "\n";

		// !ENDTRNS line
		//$content .= '!ENDTRNS' . "\n";

		$export_dates = array_keys( $_POST['period'] );

		foreach ( $export_dates as $export_date ) {
			$a = explode( '-', $export_date );
			$year = $a[0];
			$month = $a[1];

			$sql = "SELECT ID FROM " . WPSC_TABLE_PURCHASE_LOGS . ' WHERE MONTH( FROM_UNIXTIME( date ) ) = ' . $month . ' AND YEAR( FROM_UNIXTIME( DATE ) ) = '. $year . ' ORDER by date DESC';
			$result = $wpdb->get_col( $sql, 0 );
			$purchase_log_ids = array_map( 'intval', $result );

				$max_rows = 1;
				foreach ( $purchase_log_ids as $purchase_log_id ) {
					$purchase_log = new WPSC_Purchase_Log( $purchase_log_id );

					$gateway_id = $purchase_log->get( 'gateway' );
					$data = $purchase_log->get_data();

					if ( empty( $gateway_accounts[$gateway_id] ) ) {
						continue;
					}

					// reset the transaction array back to empty
					foreach ( $trans as $key => $value ) {
						$trans[$key] = '';
					}

					// reset the customer array back to empty
					foreach ( $cust as $key => $value ) {
						$cust[$key] = '';
					}

					if ( ($purchase_log->get('processed') != WPSC_Purchase_Log::ACCEPTED_PAYMENT) && ($purchase_log->get('processed') != WPSC_Purchase_Log::CLOSED_ORDER) ) {
						continue;
					}

					$checkout_form_data = new WPSC_Checkout_Form_Data ( $purchase_log_id );
					$checkout = $checkout_form_data->get_data();

					if ( !isset( $checkout['billingstate'] ) ) {
						$checkout['billingstate'] = '';
					}

					if ( !isset( $checkout['shippingstate'] ) ) {
						$checkout['shippingstate'] = '';
					}

					$timestamp = $purchase_log->get( 'date' );
					$thedate = date( 'm/d/Y' , $timestamp );

					foreach ( $trans as $key => $value ) {
						switch ( $key ) {
							case 'TRNSID':
								$trans[$key] = $trnsid++;
								break;

							case 'TIMESTAMP':
								 $trans[$key] = $purchase_log->get( 'date' );
								 break;

							case 'TRNSTYPE':
								$trans[$key] = 'CASH SALE';
								break;

							case 'DATE':
								$trans[$key] = $thedate;
								break;

							case 'ACCNT':
								$trans[$key] = $gateway_accounts[$gateway_id];
								break;

							case 'NAME':
								$trans[$key] = $checkout['billingfirstname'] . ' ' . $checkout['billinglastname'];
								break;

							case 'AMOUNT':
								$trans[$key] = $purchase_log->get( 'totalprice' );
								break;

							case 'CLEAR':
								$trans[$key] = 'N';
								break;

							case 'SHIPDATE':
								$trans[$key] = $thedate;
								break;

							case 'PAYMETH':
								$trans[$key] = $purchase_log->get( 'gateway_name' );
								break;

							case 'DOCNUM':
								$trans[$key] = $purchase_log_id;
								break;

							case 'MEMO':
								$trans[$key] = 'sparkle-gear.com purchase #'.$purchase_log_id;
								break;

							case 'ADDR1':
								$trans[$key] = $checkout['billingfirstname'] . ' ' . $checkout['billinglastname'];
								break;

							case 'ADDR2':
								$trans[$key] = $checkout['billingaddress'];
								break;

							case 'ADDR3':
								$trans[$key] = $checkout['billingcity'] . ', ' . $checkout['billingstate'] . ' ' . $checkout['billingpostcode'];
								break;

							case 'ADDR4':
								$trans[$key] = $checkout['billingcountry'];
								break;

							case 'SHIPVIA':
								$trans[$key] = $purchase_log->get( 'shipping_method_name' );
								break;

							case 'INVTITLE':
								$trans[$key] = 'Sparkle Gear Web Store';
								break;

							case 'SADDR1':
								$trans[$key] = $checkout['shippingfirstname'] . ' ' . $checkout['shippinglastname'];
								break;

							case 'SADDR2':
								$trans[$key] = $checkout['shippingaddress'];
								break;

							case 'SADDR3':
								$trans[$key] = $checkout['shippingcity'] . ', ' . $checkout['shippingstate'] . ' ' . $checkout['shippingpostcode'];
								break;

							case 'SADDR4':
								$trans[$key] = $checkout['billingcountry'];
								break;
						}
					}

					foreach ( $cust as $key => $value ) {
						switch ( $key ) {
							case 'NAME':
								$cust[$key] = $checkout['billingfirstname'] . ' ' . $checkout['billinglastname'];
								break;

							case 'FIRSTNAME':
								$cust[$key] = $checkout['billingfirstname'];
								break;

							case 'LASTNAME':
								$cust[$key] = $checkout['billinglastname'];
								break;

							case 'EMAIL':
								$cust[$key] = $checkout['billingemail'];
								break;

							case 'PHONE1':
								$cust[$key] = $checkout['billingphone'];
								break;

							case 'BADDR1':
								$cust[$key] = $checkout['billingfirstname'] . ' ' . $checkout['billinglastname'];
								break;

							case 'BADDR2':
								$cust[$key] = $checkout['billingaddress'];
								break;

							case 'BADDR3':
								$cust[$key] = $checkout['billingcity'] . ', ' . $checkout['billingstate'] . ' ' . $checkout['billingpostcode'];
								break;

							case 'BADDR4':
								$cust[$key] = $checkout['billingcountry'];
								break;

							case 'SADDR1':
								$cust[$key] = $checkout['shippingfirstname'] . ' ' . $checkout['shippinglastname'];
								break;

							case 'SADDR2':
								$cust[$key] = $checkout['shippingaddress'];
								break;

							case 'SADDR3':
								$cust[$key] = $checkout['shippingcity'] . ', ' . $checkout['shippingstate'] . ' ' . $checkout['shippingpostcode'];
								break;

							case 'SADDR4':
								$cust[$key] = $checkout['billingcountry'];
								break;
						}
					}

					foreach ( $trans as $key => $value ) {
						$trans[$key] = trim(preg_replace('/\s+/', ' ', $value));
					}

					foreach ( $cust as $key => $value ) {
						$cust[$key] = trim(preg_replace('/\s+/', ' ', $value));
					}

					$splid = 1;

					// TRNS line
					$content .= 'TRNS';
					foreach ( $trans as $key => $value ) {
						$content .= "\t" . $value;
					}
					$content .= "\n";


					$cart_contents = $purchase_log->get_cart_contents();

					foreach ( $cart_contents as $cart_item ) {
						$product_id = $cart_item->prodid;
						if ( $parent_product = get_post_field( 'post_parent', $product_id ) ) {
							$product_id = $parent_product;
						}

						$terms = wp_get_post_terms( $product_id, 'wpsc_product_category' );

						if ( !empty ( $terms ) ) {
							foreach ( $terms as $term ) {
								$invitem = $term->name;
								if ( $term->parent != 0 ) {
									break;
								}
							}
						} else {
							$invitem ='';
						}

						/*
						$item_name = '';

						$article = new Bling_Article( $cart_item->prodid );
						if ( $article->check() ) {
							$item_name = $article->name();
						}
						*/
						$spl_product = array (
								'SPLID'         => $trnsid++,
								'TRNSTYPE'      => 'PAYMENT',
								'DATE'          => $trans['DATE'],
								'ACCNT'         => $export_accounts['sales_revenue'],
								'AMOUNT'        => -($cart_item->price *  $cart_item->quantity), // -($purchase_log->get( 'totalprice' ) - $purchase_log->get( 'wpec_taxes_total' ) - $purchase_log->get( 'total_shipping' )),
								'QNTY'          => -$cart_item->quantity,
								'PRICE'         => $cart_item->price,
								'NAME'          => '',
								'DOCNUM'        => $purchase_log_id,
								'MEMO'          => $cart_item->name,
								//'INVITEM'       => $item_name,
						);

						// SPL line
						$content .= 'SPL';
						foreach ( $spl as $key => $value ) {
							$content .= "\t";
							if ( !empty($spl_product[$key]) )
								$content .= $spl_product[$key];
						}

						$content .= "\n";
					}

					$spl_shipping = array (
							'SPLID'         => $trnsid++,
							'TRNSTYPE'      => 'PAYMENT',
							'DATE'          => $trans['DATE'],
							'ACCNT'         => $export_accounts['shipping'],
							'AMOUNT'        => -$purchase_log->get( 'total_shipping' ),
							'PRICE'         => $purchase_log->get( 'total_shipping' ),
							'NAME'          => '',
							'DOCNUM'        => $purchase_log_id,
							'MEMO'          => 'customer paid shipping',
							'EXTRA'         => '',
							'QNTY'          => '',
//							'INVITEM'       => '',
					);

					$splid = 2;

					$spl_discount = array (
							'SPLID'         => $trnsid++,
							'TRNSTYPE'      => 'PAYMENT',
							'DATE'          => $trans['DATE'],
							'ACCNT'         => $export_accounts['sales_revenue'],
							'AMOUNT'        => $purchase_log->get( 'discount_value' ),
							'PRICE'         => -$purchase_log->get( 'discount_value' ),
							'NAME'          => '',
							'DOCNUM'        => $purchase_log_id,
							'MEMO'          => 'discount',
							'EXTRA'         => '',
							'QNTY'          => '',
							);

					$spl_tax = array (
							'SPLID'         => $trnsid++,
							'TRNSTYPE'      => 'PAYMENT',
							'DATE'          => $trans['DATE'],
							'ACCNT'         => $export_accounts['sales_tax_account'],
							'AMOUNT'        => -$purchase_log->get( 'wpec_taxes_total' ),
							'PRICE'         => "6.25%",
							'NAME'          => $export_accounts['sales_tax_payee'],
							'DOCNUM'        => $purchase_log_id,
							'MEMO'          => 'sales tax',
							'EXTRA'         => 'AUTOSTAX',
							'QNTY'          => '',
							'INVITEM'       => 'MA Sales/Use Tax',
					);

					$spl_end = array (
							'SPLID'         => $trnsid++,
							'EXTRA'         => 'ENDGRP',
					);


					// SPL line
					$content .= 'SPL';
					foreach ( $spl as $key => $value ) {
						$content .= "\t";
						if ( !empty($spl_shipping[$key]) )
							$content .= $spl_shipping[$key];
					}
					$content .= "\n";

					// SPL line
					$content .= 'SPL';
					foreach ( $spl as $key => $value ) {
						$content .= "\t";
						if ( !empty($spl_tax[$key]) )
							$content .= $spl_tax[$key];
					}
					$content .= "\n";

					// SPL line
					$content .= 'SPL';
					foreach ( $spl as $key => $value ) {
						$content .= "\t";
						if ( !empty($spl_discount[$key]) )
							$content .= $spl_discount[$key];
					}
					$content .= "\n";

					$content .= 'SPL';
					foreach ( $spl as $key => $value ) {
						$content .= "\t";
						if ( !empty($spl_end[$key]) )
							$content .= $spl_end[$key];
					}
					$content .= "\n";

					$splid = 3;
					$content .= 'ENDTRNS';
					$content .= "\n";
					//if ( --$max_rows == 0 )
					//	break;

					$cust_content .= 'CUST';
					foreach ( $cust as $key => $value ) {
						$cust_content .= "\t" . $value;
					}
					$cust_content .= "\n";

				}
			}

			$file_name = 'download.iif';
			header( 'Content-Type: text/csv' );
			header( 'Content-Disposition: inline; filename="' . $file_name . '"' );
			echo $headers;
			echo $cust_content;
			echo $content;
			exit();
	}


}


$exporter = new pbci_wpec_iif_exporter();

if ( isset( $_REQUEST['pbci_action'] ) && ($_REQUEST['pbci_action'] == 'Export') && !empty($_REQUEST['period']) ) {
	add_action( 'admin_init', array( $exporter, 'do_export_file' ) );
}

