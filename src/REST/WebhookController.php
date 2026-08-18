<?php

namespace BeplusManager\REST;

use BeplusManager\Deploy\Deployer;
use BeplusManager\Storage\PackageRepository;

/**
 * GitHub webhook receiver.
 *
 * Verifies the X-Hub-Signature-256 HMAC against the configured secret, then
 * auto-deploys the package that matches the pushed repository + branch.
 */
class WebhookController {

	const NS = 'beplus-manager/v1';

	/** @var PackageRepository */
	private $packages;

	/** @var Deployer */
	private $deployer;

	public function __construct( PackageRepository $packages, Deployer $deployer ) {
		$this->packages = $packages;
		$this->deployer = $deployer;
	}

	public function register_routes(): void {
		register_rest_route( self::NS, '/webhook', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'handle' ),
			'permission_callback' => '__return_true',
		) );
	}

	public function handle( \WP_REST_Request $request ): \WP_REST_Response {
		$secret  = (string) $this->packages->get_setting( 'webhook_secret', '' );
		$signature = $request->get_header( 'x_hub_signature_256' );
		$body    = $request->get_body();

		if ( ! $secret || ! $signature || ! hash_equals(
			'sha256=' . hash_hmac( 'sha256', $body, $secret ),
			$signature
		) ) {
			return new \WP_REST_Response( array( 'ok' => false, 'message' => 'Invalid signature.' ), 403 );
		}

		$payload = json_decode( $body, true );
		if ( ! is_array( $payload ) || empty( $payload['repository']['full_name'] ) ) {
			return new \WP_REST_Response( array( 'ok' => false, 'message' => 'Invalid payload.' ), 400 );
		}

		$repo      = $payload['repository']['full_name'];
		$ref       = $payload['ref'] ?? '';
		$branch    = str_replace( 'refs/heads/', '', $ref );
		$packages  = $this->packages->all_packages();
		$deployed  = array();

		foreach ( $packages as $pkg ) {
			if ( empty( $pkg['webhook'] ) ) {
				continue;
			}
			if ( $pkg['repository'] !== $repo ) {
				continue;
			}
			if ( $branch && $pkg['branch'] && $branch !== $pkg['branch'] ) {
				continue;
			}
			$pkg['token'] = (string) $this->packages->get_setting( 'github_token', '' );
			$result       = $this->deployer->deploy( $pkg );
			$deployed[]   = array(
				'slug'    => $pkg['slug'],
				'ok'      => $result['ok'],
				'message' => $result['message'],
			);
		}

		if ( ! $deployed ) {
			return new \WP_REST_Response( array( 'ok' => false, 'message' => 'No matching package.' ), 404 );
		}
		return new \WP_REST_Response( array( 'ok' => true, 'deployed' => $deployed ) );
	}
}
