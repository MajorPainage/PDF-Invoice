<?php
/**
 * PDF Invoice by opencart-templates
 */
class ModelExtensionModulePdfInvoice extends Model {

	public function getInvoice($orders, $create_file = false, $admin_download = false) {
		if (!$this->config->get('module_pdf_invoice_status')) {
			return false;
		}

        if (!is_array($orders)) {
            $orders = array($orders);
        }

		$this->load->library('pdf_invoice');

		$this->load->model('sale/order');
		$this->load->model('setting/setting');
		$this->load->model('localisation/language');
		$this->load->model('localisation/order_status');
		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		$module_pdf_invoice = $this->model_setting_setting->getSetting("module_pdf_invoice");

		$languages = $this->model_localisation_language->getLanguages();

		$filename = 'order';

		$orders_iteration = 0;

		foreach($orders as $order) {
			if (is_numeric($order)) {
				$order_info = $this->model_sale_order->getOrder($order);
			} else {
				$order_info = $order;
			}

			if (!$order_info) {
				continue;
			}

			$filename .= '_' . $order_info['order_id'];

			$data = array();

			$data['config'] = $module_pdf_invoice;

			$data['order'] = $order_info;

			$data['orders'] = count($orders);

			$orders_iteration++;
			$data['orders_iteration'] = $orders_iteration;

			$data['language_id'] = ($order_info['language_id'] && !$admin_download) ? $order_info['language_id'] : $this->config->get('config_language_id');

			$data['store_id'] = ($order_info['store_id']) ? $order_info['store_id'] : 0;

			foreach ($languages as $lang) {
				if ($lang['language_id'] == $data['language_id']) {
					$oLanguage = new Language($lang['code']);

					$oLanguage->load($lang['code']);
					$oLanguage->load('sale/order');
					$oLanguage->load('extension/module/pdf_invoice');

					continue;
				}
			}

			if (!isset($oLanguage)) {
				trigger_error("Error: unable to find language = '{$data['language_id']}'");
				return false;
			}

			$data['order']['shipping_method'] = strip_tags($data['order']['shipping_method']);
			$data['order']['payment_method'] = strip_tags($data['order']['payment_method']);

			$data['order']['date_added'] = date($oLanguage->get('date_format_short'), strtotime($data['order']['date_added']));

			$data['order']['comment'] = strip_tags(html_entity_decode($data['order']['comment'], ENT_QUOTES, 'UTF-8'));

			$order_status_info = $this->model_localisation_order_status->getOrderStatus($order_info['order_status_id']);

			if ($order_status_info) {
				$data['order']['order_status'] = $order_status_info['name'];
			} else {
				$data['order']['order_status'] = '';
			}

			$data['order']['totals'] = array();

			$totals = $this->model_sale_order->getOrderTotals($order_info['order_id']);

			if ($totals) {
				foreach ($totals as $total) {
					$data['order']['totals'][] = array(
						'title' => $total['title'],
						'text' => (!empty($total['text']) ? $total['text'] : $this->currency->format($total['value'], $data['order']['currency_code'], $data['order']['currency_value'])),
					);
				}
			}

			if (isset($data['config']['module_pdf_invoice_rtl_' . $data['language_id']])) {
				$data['config']['text_align'] = 'right';
			} else {
				$data['config']['text_align'] = 'left';
			}

			$data['store'] = $this->model_setting_setting->getSetting("config", $data['store_id']);

			unset($data['store']['config_robots']);

            $logo_width = isset($data['config']['module_pdf_invoice_logo_width']) ? $data['config']['module_pdf_invoice_logo_width'] : 200;
            $logo_height = isset($data['config']['module_pdf_invoice_logo_height']) ? $data['config']['module_pdf_invoice_logo_height'] : 60;

			if ($data['config']['module_pdf_invoice_logo']) {
                $data['store']['config_logo'] = $this->_resize($data['config']['module_pdf_invoice_logo'], $logo_width, $logo_height);
			} elseif ($this->config->get('config_logo') && $logo_width && $logo_height) {
                $data['store']['config_logo'] = $this->_resize($this->config->get('config_logo'), $logo_width, $logo_height);
			} else {
				$data['store']['config_logo'] = false;
			}

			if ($data['store']['config_address']) {
				$data['store']['config_address'] = nl2br($data['store']['config_address']);
			}

			// Custom fields
			if (file_exists(DIR_APPLICATION . 'model/customer/custom_field.php')) {
				$this->load->model('customer/custom_field');

				$custom_fields = $this->model_customer_custom_field->getCustomFields($data['order']['customer_group_id']);

				foreach ($custom_fields as $custom_field) {
					if (isset($data['order']['custom_field'][$custom_field['custom_field_id']])) {
						$data['order']['custom_field'][$custom_field['custom_field_id']] = array(
							'name' => $custom_field['name'],
							'value' => $data['order']['custom_field'][$custom_field['custom_field_id']]
						);
					}

					if (isset($data['order']['shipping_custom_field'][$custom_field['custom_field_id']])) {
						$data['order']['shipping_custom_field'][$custom_field['custom_field_id']] = array(
							'name' => $custom_field['name'],
							'value' => $data['order']['shipping_custom_field'][$custom_field['custom_field_id']]
						);
					}

					if (isset($data['order']['payment_custom_field'][$custom_field['custom_field_id']])) {
						$data['order']['payment_custom_field'][$custom_field['custom_field_id']] = array(
							'name' => $custom_field['name'],
							'value' => $data['order']['payment_custom_field'][$custom_field['custom_field_id']]
						);
					}
				}
			}

			$data['order']['shipping_address'] = $this->formatAddress($data['order'], 'shipping', $data['order']['shipping_address_format']);
			$data['order']['payment_address'] = $this->formatAddress($data['order'], 'payment', $data['order']['payment_address_format']);

			$data['order']['products'] = array();

			$products = $this->model_sale_order->getOrderProducts($order_info['order_id']);

			if ($products) {
				foreach ($products as $product) {
					$product_data = $this->model_catalog_product->getProduct($product['product_id']);

					$option_data = array();
					$options = $this->model_sale_order->getOrderOptions($order_info['order_id'], $product['order_product_id']);

					foreach ($options as $option) {
						if ($option['type'] != 'file') {
							$value = $option['value'];
						} else {
							$value = utf8_substr($option['value'], 0, utf8_strrpos($option['value'], '.'));
						}
						$option_data[] = array(
							'name' => $option['name'],
							'value' => $value
						);
					}

					$option_string = '';
					if (count($option_data) > 0) {
						foreach ($option_data as $value) {
							$option_string .= '<br />' . $value['name'] . ': ' . $value['value'];
						}
					}

                    $image = false;
                    if (!empty($data['config']['module_pdf_invoice_order_image']) && !empty($product_data['image'])) {
                        $image_width = isset($data['config']['module_pdf_invoice_order_image_width']) ? $data['config']['module_pdf_invoice_order_image_width'] : 200;
                        $image_height = isset($data['config']['module_pdf_invoice_order_image_height']) ? $data['config']['module_pdf_invoice_order_image_height'] : 200;
                        $image = $this->_resize($product_data['image'], $image_width, $image_height);
                    }

					if (!empty($data['config']['module_pdf_invoice_barcode']) && !empty($product_data['sku'])) {
						$params = $this->pdf_invoice->tcpdf->serializeTCPDFtagParameters(array($product_data['sku'], 'C128B', '', '', 0, 0, 0.2, array('position' => 'S', 'stretch' => true, 'fitwidth' => true, 'cellfitalign' => 'C', 'position' => 'C', 'align' => 'C', 'border' => false, 'padding' => 2, 'fgcolor' => array(0, 0, 0), 'bgcolor' => array(255, 255, 255), 'text' => true), 'N'));
						$barcode = '<div><tcpdf method="write1DBarcode" params="'.$params. '" /></div>';
					} else {
						$barcode = false;
					}

					$data['order']['products'][] = array_merge($product_data, array(
						'name' => '<b>' . $product['name'] . '</b>',
						'model' => $product['model'],
						'sku' => isset($product_data['sku']) ? $product_data['sku'] : '',
						'option' => $option_string,
						'image' => $image,
						'barcode' => $barcode,
						'quantity' => $product['quantity'],
						'url' => HTTP_CATALOG . 'index.php?route=product/product&product_id=' . $product['product_id'],
						'price' => $this->currency->format($product['price'], $data['order']['currency_code'], $data['order']['currency_value']),
						'total' => $this->currency->format($product['total'], $data['order']['currency_code'], $data['order']['currency_value']),
						'price_with_tax' => $this->currency->format($product['price'] + ($this->config->get('config_tax') ? $product['tax'] : 0), $data['order']['currency_code'], $data['order']['currency_value']),
						'total_with_tax' => $this->currency->format($product['total'] + ($this->config->get('config_tax') ? ($product['tax'] * $product['quantity']) : 0), $data['order']['currency_code'], $data['order']['currency_value'])
					));
				}
			}

			// Order - Vouchers
			$data['order']['vouchers'] = array();

			$vouchers = $this->model_sale_order->getOrderVouchers($order_info['order_id']);

			if ($vouchers) {
				foreach ($vouchers as $voucher) {
					$data['order']['vouchers'][] = array(
						'description' => $voucher['description'],
						'amount' => $this->currency->format($voucher['amount'], $data['order']['currency_code'], $data['order']['currency_value'])
					);
				}
			}

			$language = array();

			$language['a_meta_charset'] = 'UTF-8';

			$language['text_date_added'] = $oLanguage->get('text_date_added');
			$language['text_order_id'] = $oLanguage->get('text_order_id');
			$language['text_order_status'] = $oLanguage->get('text_order_status');
			$language['text_invoice_no'] = $oLanguage->get('text_invoice_no');
			$language['text_shipping_method'] = $oLanguage->get('text_shipping_method');
			$language['text_shipping_address'] = $oLanguage->get('text_shipping_address');
			$language['text_payment_method'] = $oLanguage->get('text_payment_method');
			$language['text_payment_address'] = $oLanguage->get('text_payment_address');

			$language['column_total'] = $oLanguage->get('column_total');
			$language['column_product'] = $oLanguage->get('column_product');
			$language['column_model'] = $oLanguage->get('column_model');
			$language['column_quantity'] = $oLanguage->get('column_quantity');
			$language['column_price'] = $oLanguage->get('column_price');

			$data = array_merge($data, $language);

			$this->pdf_invoice->tcpdf->setLanguageArray($language);

			$this->pdf_invoice->data = $data;

			$template_filename = 'extension/module/pdf_invoice/pdf_invoice';

			if (!empty($data['config']['module_pdf_invoice_rtl_' . $data['language_id']])) {
				$template_filename .= '_rtl';
			}

			$this->pdf_invoice->data['html'] = $this->load->view($template_filename, $data);


			$this->pdf_invoice->Draw();

			if (ob_get_length()) ob_end_clean();
		}

		if (empty($this->pdf_invoice->data)) {
			return false;
		}

		if ($create_file) {
			$dir = DIR_CACHE . 'invoices/';
			if (!is_dir($dir) || !is_writable($dir)) {
				mkdir($dir, 0777, true);
			}
			if (!is_dir($dir)) {
				trigger_error('Permissions Error: couldn\'t create directory \'invoices\' at: ' . $dir);
				return false;
			}

			if (file_exists($dir.$filename . '.pdf')) {
				unlink($dir.$filename . '.pdf');
			}

			$this->pdf_invoice->Output($dir.$filename . '.pdf', 'F');

			return $dir.$filename . '.pdf';
		} else {
			$this->pdf_invoice->Output($filename . '.pdf', 'I');

			return true;
		}
	}

    /**
     * Handle resizing image and returning file path
     * @param $file
     * @param int $width
     * @param int $height
     * @return string
     */
    private function _resize($file, $width = 100, $height = 100) {
        $width = $width ? $width : 100;
        $height = $height ? $height : 100;

        if (!file_exists(DIR_IMAGE . $file)) {
            trigger_error('PDF Invoice missing image file: ' . $file);
            return false;
        }

        if (!$width) {
            $width = 100;
        }
        if (!$height) {
            $height = 100;
        }

        $this->load->model('tool/image');

        $logo_size = getimagesize(DIR_IMAGE . $file);

        $imageWidth  = $logo_size[0];
        $imageHeight = $logo_size[1];

        $wRatio = $imageWidth / (int)$width;
        $hRatio = $imageHeight / (int)$height;
        $maxRatio = max($wRatio, $hRatio);

        if ($maxRatio > 1) {
            $outputWidth = round($imageWidth / $maxRatio);
            $outputHeight = round($imageHeight / $maxRatio);
        } else {
            $outputWidth = $imageWidth;
            $outputHeight = $imageHeight;
        }

        $image = $this->model_tool_image->resize($file, $outputWidth, $outputHeight);

        // Convert to path instead of url
        $image = '/' . str_replace(array(HTTPS_CATALOG, HTTP_CATALOG), '', $image);

        return $image;
    }

	public function formatAddress($address, $address_prefix = '', $format = null) {
		$find = array();
		$replace = array();

		if ($address_prefix != "") {
			$address_prefix = trim($address_prefix, '_') . '_';
		}

		if (is_null($format) || !is_string($format) || $format == '') {
			$format = '{firstname} {lastname}' . "\n" . '{company}' . "\n" . '{address_1}' . "\n" . '{address_2}' . "\n" . '{city} {postcode}' . "\n" . '{zone}' . "\n" . '{country}';
		}

		$vars = array(
			'firstname',
			'lastname',
			'telephone',
			'company',
			'address_1',
			'address_2',
			'city',
			'postcode',
			'zone',
			'zone_code',
			'country'
		);

        foreach ($vars as $var) {
            if ($address_prefix && isset($address[$address_prefix.$var])) {
                $value = $address[$address_prefix.$var];
            } elseif (isset($address[$var])) {
                $value = $address[$var];
            } else {
                $value = '';
            }

            if (is_numeric($value) || is_string($value)|| is_null($value)|| is_bool($value)) {
                $find[$var] = '{'.$var.'}';
                $replace[$var] = $value;
            }
        }

        foreach(array('custom_field', $address_prefix . 'custom_field') as $var) {
            if (isset($address[$var]) && is_array($address[$var])) {
                foreach ($address[$var] as $custom_field_id => $custom_field) {
                    if (!isset($custom_field['value'])) {
                        continue;
                    }

                    $var = 'custom_field_' . $custom_field_id;
                    $value = $custom_field['value'];

                    if (is_numeric($value) || is_string($value) || is_null($value) || is_bool($value)) {
                        $find[$var] = '{custom_field_' . $custom_field_id . '}';
                        $replace[$var] = $value;
                    }
                }
            }
        }

		return trim(str_replace(array("\r\n", "\r", "\n"), '<br />', preg_replace(array("/\s\s+/", "/\r\r+/", "/\n\n+/"), '<br />', str_replace($find, $replace, $format))));
	}
}
