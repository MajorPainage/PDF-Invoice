<?php
class ControllerExtensionModulePdfInvoice extends Controller {
	private $error = array();

    private $fields = array(
        'admin',
        'attach',
        'barcode',
        'border_color',
        'color',
        'complete',
        'download',
        'font',
        'font_size',
        'logo',
        'logo_font',
        'logo_font_size',
        'logo_height',
        'logo_width',
        'order_complete',
        'order_image',
        'order_image_height',
        'order_image_width',
        'paging',
        'status'
    );

    private $language_fields = array(
        'after',
        'append',
        'before',
        'footer',
        'header',
        'prepend',
        'rtl',
        'title'
    );

	public function index() {
		$this->load->language('extension/module/pdf_invoice');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('module_pdf_invoice', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('extension/module/pdf_invoice', 'user_token=' . $this->session->data['user_token'], true));
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_module'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_name'),
			'href' => $this->url->link('extension/module/pdf_invoice', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/module/pdf_invoice', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'], true);

		$this->load->model('tool/image');
		$this->load->model('localisation/language');

		$data['languages'] = $this->model_localisation_language->getLanguages();

        $data['fonts'] = $this->_getTcpdfFonts();

		foreach($this->fields as $field) {
            if (isset($this->request->post['module_pdf_invoice_' . $field])) {
                $data['module_pdf_invoice_' . $field] = $this->request->post['module_pdf_invoice_' . $field];
            } else {
                $data['module_pdf_invoice_' . $field] = $this->config->get('module_pdf_invoice_' . $field);
            }
        }

		if (isset($this->request->post['module_pdf_invoice_logo']) && is_file(DIR_IMAGE . $this->request->post['module_pdf_invoice_logo'])) {
			$data['logo_thumb'] = $this->model_tool_image->resize($this->request->post['module_pdf_invoice_logo'], 100, 100);
		} elseif ($this->config->get('module_pdf_invoice_logo') && is_file(DIR_IMAGE . $this->config->get('module_pdf_invoice_logo'))) {
			$data['logo_thumb'] = $this->model_tool_image->resize($this->config->get('module_pdf_invoice_logo'), 100, 100);
		} else {
			$data['logo_thumb'] = $this->model_tool_image->resize('no_image.png', 100, 100);
		}

		$data['placeholder'] = $this->model_tool_image->resize('no_image.png', 100, 100);

		foreach ($data['languages'] as $language) {
			$data['module_pdf_invoice_preview'][$language['language_id']] = $this->url->link('extension/module/pdf_invoice/preview', 'user_token=' . $this->session->data['user_token'] . '&language_id=' . $language['language_id'], true);

            foreach($this->language_fields as $language_field) {
                if (isset($this->request->post['module_pdf_invoice_' . $language_field  . '_' . $language['language_id']])) {
                    $data['module_pdf_invoice_' . $language_field  . '_' . $language['language_id']] = $this->request->post['module_pdf_invoice_' . $language_field  . '_' . $language['language_id']];
                } else {
                    $data['module_pdf_invoice_' . $language_field  . '_' . $language['language_id']] = $this->config->get('module_pdf_invoice_' . $language_field  . '_' . $language['language_id']);
                }
            }
		}

		$this->document->addStyle('view/javascript/bootstrap/css/bootstrap-colorpicker.min.css');
		$this->document->addStyle('view/stylesheet/module/pdf_invoice.css');
		$this->document->addScript('view/javascript/bootstrap/js/bootstrap-colorpicker.min.js');
		$this->document->addStyle('view/javascript/summernote/summernote.css');
		$this->document->addScript('view/javascript/summernote/summernote.js');
		$this->document->addScript('view/javascript/summernote/opencart.js');
		$this->document->addScript('view/javascript/module/pdf_invoice.js');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/pdf_invoice/extension', $data));
	}

	public function preview() {
		$this->load->model('extension/module/pdf_invoice');

		$this->load->model('sale/order');

		$order_id = 0;

		if (!empty($this->request->get['order_id'])) {
			$order_id = $this->request->get['order_id'];
		} else {
			$order_statuses = $this->config->get('config_complete_status');

			foreach ($order_statuses as $order_status_id) {
				$implode[] = "o.order_status_id = '" . (int)$order_status_id . "'";
			}

			if ($implode) {
				$result = $this->db->query("SELECT o.order_id FROM `" . DB_PREFIX . "order` o WHERE (" . implode(" OR ", $implode) . ") ORDER BY o.order_id DESC LIMIT 1");

				if ($result->row) {
					$order_id = $result->row['order_id'];
				} else {
					// Get any order
					$result = $this->db->query("SELECT o.order_id FROM `" . DB_PREFIX . "order` o ORDER BY o.order_id DESC LIMIT 1");

					if ($result->row) {
						$order_id = $result->row['order_id'];
					} else {
						trigger_error("Warning: requires at least one COMPLETE order to preview invoice pdf!");
						exit;
					}
				}
			}
		}

		$order_info = $this->model_sale_order->getOrder($order_id);

		if (!$order_info) {
			trigger_error("Warning: unable to find order = '{$order_id}'");
			return false;
		}

		// Overwrite language_id
		if (!empty($this->request->get['language_id'])) {
			$this->load->model('localisation/language');

			$language_info = $this->model_localisation_language->getLanguage($this->request->get['language_id']);

			if ($language_info) {
				$order_info['language_id'] = $language_info['language_id'];
				$order_info['language_code'] = $language_info['code'];
			}
		}

		echo $this->model_extension_module_pdf_invoice->getInvoice(array($order_info), false);
		exit(0);
	}

	public function generate() {
		if (isset($this->request->post['selected'])) {
			$selected = $this->request->post['selected'];
		} elseif (isset($this->request->get['selected'])) {
			$selected = $this->request->get['selected'];
		} elseif (isset($this->request->get['order_id'])) {
			$selected = array($this->request->get['order_id']);
		}

		if (!empty($selected)) {
			$this->load->model('extension/module/pdf_invoice');
			echo $this->model_extension_module_pdf_invoice->getInvoice($selected, false, true);
			exit(0);
		}
	}

	public function install() {
		// Save default settings
		$data = array(
			'module_pdf_invoice_status' => 1,
			'module_pdf_invoice_color' => '#23a1d1',
			'module_pdf_invoice_download' => 1,
			'module_pdf_invoice_attach' => 1,
			'module_pdf_invoice_order_complete' => 1,
			'module_pdf_invoice_order_image' => 1,
			'module_pdf_invoice_logo' => $this->config->get('config_logo'),
			'module_pdf_invoice_header' => '',
			'module_pdf_invoice_footer' => ''
		);

		$this->load->model('setting/setting');

		$this->model_setting_setting->editSetting('module_pdf_invoice', $data);

		return true;
	}

	public function uninstall() {
		$this->load->model('setting/setting');

		$this->model_setting_setting->deleteSetting('module_pdf_invoice');
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/pdf_invoice')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

        // Check font exists
        if (!empty($this->request->post['module_pdf_invoice_font']) && !file_exists(DIR_SYSTEM . 'library/shared/tcpdf/fonts/' . $this->request->post['module_pdf_invoice_font'] . '.php')) {
            $this->error['warning'] = sprintf($this->language->get('error_font'), $this->request->post['module_pdf_invoice_font']);
        }

		return !$this->error;
	}

    /**
     * Get tcpdf fonts from path: library/shared/tcpdf/fonts
     * @return array
     */
    protected function _getTcpdfFonts() {
        $fonts = array();

        $files = glob(DIR_SYSTEM . 'library/shared/tcpdf/fonts/*.php', GLOB_BRACE);

        $suffixes = array('bi', 'b', 'i');

        if ($files) {
            foreach ($files as $file) {
                $base_name = basename($file, '.php');

                foreach($suffixes as $suffix) {
                    $length = strlen($suffix);
                    if (substr($base_name, -$length) === $suffix) {
                        $base_name = substr($base_name, 0, strlen($base_name)-$length);
                    }
                }

                if (!isset($fonts[$base_name])) {
                    $fonts[$base_name] = array();
                }

                $fonts[$base_name][] = basename($file, '.php');
            }
        }

        return $fonts;
    }
}
