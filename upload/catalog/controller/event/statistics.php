<?php
/**
 * Class Statistics
 *
 * @package Catalog\Controller\Event
 */
class ControllerEventStatistics extends Controller {
	/**
	 * Add Review
	 *
	 * @param string               $route
	 * @param array<string, mixed> $args
	 * @param mixed                $output
	 *
	 * @return void
	 *
	 * catalog/model/catalog/review/addReview/after
	 */
	public function addReview(string &$route, array &$args, &$output): void {
		// Statistics
		$this->load->model('report/statistics');

		$this->model_report_statistics->addValue('review', 1);
	}

	/**
	 * Add Return
	 *
	 * @param string               $route
	 * @param array<string, mixed> $args
	 * @param mixed                $output
	 *
	 * @return void
	 *
	 * catalog/model/account/returns/addReturn/after
	 */
	public function addReturn(string &$route, array &$args, &$output): void {
		// Statistics
		$this->load->model('report/statistics');

		$this->model_report_statistics->addValue('returns', 1);
	}

	/**
	 * Add History
	 *
	 * @param string               $route
	 * @param array<string, mixed> $args
	 * @param mixed                $output
	 *
	 * @return void
	 *
	 * catalog/model/checkout/order/addHistory/before
	 */
	public function addHistory(string &$route, array &$args, &$output): void {
		// Orders
		$this->load->model('checkout/order');

		$order_info = $this->model_checkout_order->getOrder($args[0]);

		if ($order_info) {
			// Statistics
			$this->load->model('report/statistics');

			// If order status is in complete, or processing state, add value to sale total
			if (in_array($args[1], array_merge((array)$this->config->get('config_processing_status'), (array)$this->config->get('config_complete_status')))) {
				$this->model_report_statistics->addValue('order_sale', $order_info['total']);
			}

			// If order status is not in complete, or processing state, remove value to sale total
			if (!in_array($args[1], array_merge((array)$this->config->get('config_processing_status'), (array)$this->config->get('config_complete_status')))) {
				$this->model_report_statistics->removeValue('order_sale', $order_info['total']);
			}

			// Remove from processing status if new status is not in the array
			if (in_array($order_info['order_status_id'], (array)$this->config->get('config_processing_status')) && !in_array($args[1], (array)$this->config->get('config_processing_status'))) {
				$this->model_report_statistics->removeValue('order_processing', 1);
			}

			// Add to processing status if new status is not in the array
			if (!in_array($order_info['order_status_id'], (array)$this->config->get('config_processing_status')) && in_array($args[1], (array)$this->config->get('config_processing_status'))) {
				$this->model_report_statistics->addValue('order_processing', 1);
			}

			// Remove from complete status if new status is not in the array
			if (in_array($order_info['order_status_id'], (array)$this->config->get('config_complete_status')) && !in_array($args[1], (array)$this->config->get('config_complete_status'))) {
				$this->model_report_statistics->removeValue('order_complete', 1);
			}

			// Add to complete status if new status is not the array
			if (!in_array($order_info['order_status_id'], (array)$this->config->get('config_complete_status')) && in_array($args[1], (array)$this->config->get('config_complete_status'))) {
				$this->model_report_statistics->addValue('order_complete', 1);
			}
		}
	}
}
