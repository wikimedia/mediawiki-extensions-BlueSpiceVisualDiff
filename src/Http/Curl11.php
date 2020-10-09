<?php

namespace BlueSpice\VisualDiff\Http;

use CurlHttpRequest;
use InvalidArgumentException;
use Status;

/**
 * Overwrite hardcoded usage of CURL_HTTP_VERSION_1_0
 * $this->curlOptions[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_1;
 */
class Curl11 extends CurlHttpRequest {
	public function execute() {
		$this->prepare();

		if ( !$this->status->isOK() ) {
			// TODO B/C; move this to callers
			return Status::wrap( $this->status );
		}

		$this->curlOptions[CURLOPT_PROXY] = $this->proxy;
		$this->curlOptions[CURLOPT_TIMEOUT] = $this->timeout;
		$this->curlOptions[CURLOPT_CONNECTTIMEOUT_MS] = $this->connectTimeout * 1000;
		$this->curlOptions[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_1;
		$this->curlOptions[CURLOPT_WRITEFUNCTION] = $this->callback;
		$this->curlOptions[CURLOPT_HEADERFUNCTION] = [ $this, "readHeader" ];
		$this->curlOptions[CURLOPT_MAXREDIRS] = $this->maxRedirects;
		# Enable compression
		$this->curlOptions[CURLOPT_ENCODING] = "";

		$this->curlOptions[CURLOPT_USERAGENT] = $this->reqHeaders['User-Agent'];

		$this->curlOptions[CURLOPT_SSL_VERIFYHOST] = $this->sslVerifyHost ? 2 : 0;
		$this->curlOptions[CURLOPT_SSL_VERIFYPEER] = $this->sslVerifyCert;

		if ( $this->caInfo ) {
			$this->curlOptions[CURLOPT_CAINFO] = $this->caInfo;
		}

		if ( $this->headersOnly ) {
			$this->curlOptions[CURLOPT_NOBODY] = true;
			$this->curlOptions[CURLOPT_HEADER] = true;
		} elseif ( $this->method == 'POST' ) {
			$this->curlOptions[CURLOPT_POST] = true;
			$postData = $this->postData;
			// Don't interpret POST parameters starting with '@' as file uploads, because this
			// makes it impossible to POST plain values starting with '@' (and causes security
			// issues potentially exposing the contents of local files).
			$this->curlOptions[CURLOPT_SAFE_UPLOAD] = true;
			$this->curlOptions[CURLOPT_POSTFIELDS] = $postData;

			// Suppress 'Expect: 100-continue' header, as some servers
			// will reject it with a 417 and Curl won't auto retry
			// with HTTP 1.0 fallback
			$this->reqHeaders['Expect'] = '';
		} else {
			$this->curlOptions[CURLOPT_CUSTOMREQUEST] = $this->method;
		}

		$this->curlOptions[CURLOPT_HTTPHEADER] = $this->getHeaderList();

		$curlHandle = curl_init( $this->url );

		if ( !curl_setopt_array( $curlHandle, $this->curlOptions ) ) {
			$this->status->fatal( 'http-internal-error' );
			throw new InvalidArgumentException( "Error setting curl options." );
		}

		if ( $this->followRedirects && $this->canFollowRedirects() ) {
			Wikimedia\suppressWarnings();
			if ( !curl_setopt( $curlHandle, CURLOPT_FOLLOWLOCATION, true ) ) {
				$this->logger->debug( __METHOD__ . ": Couldn't set CURLOPT_FOLLOWLOCATION. " .
					"Probably open_basedir is set." );
				// Continue the processing. If it were in curl_setopt_array,
				// processing would have halted on its entry
			}
			Wikimedia\restoreWarnings();
		}

		if ( $this->profiler ) {
			$profileSection = $this->profiler->scopedProfileIn(
				__METHOD__ . '-' . $this->profileName
			);
		}

		$curlRes = curl_exec( $curlHandle );
		if ( curl_errno( $curlHandle ) == CURLE_OPERATION_TIMEOUTED ) {
			$this->status->fatal( 'http-timed-out', $this->url );
		} elseif ( $curlRes === false ) {
			$this->status->fatal( 'http-curl-error', curl_error( $curlHandle ) );
		} else {
			$this->headerList = explode( "\r\n", $this->headerText );
		}

		curl_close( $curlHandle );

		if ( $this->profiler ) {
			$this->profiler->scopedProfileOut( $profileSection );
		}

		$this->parseHeader();
		$this->setStatus();

		// TODO B/C; move this to callers
		return Status::wrap( $this->status );
	}
}
