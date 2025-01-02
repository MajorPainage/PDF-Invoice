<?php
class ControllerExtensionModulePdfInvoice extends Controller {
	public function index() {
		return $this->generate();
	}

	public function generate() {
		if (!isset($this->request->get['order_id'])) return false;

		$this->load->model('extension/module/pdf_invoice');

		echo $this->model_extension_module_pdf_invoice->getInvoice(array($this->request->get['order_id']), false);
		exit(0);
	}
}