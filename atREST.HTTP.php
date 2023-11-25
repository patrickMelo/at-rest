<?php

namespace atREST;

/** Static class used for HTTP related constants and methods.
 */

class HTTP {

    // Constants

    const Tag = 'HTTP';

	// Constants: HTTP Status Codes

	const Continue						= 100;
	const SwitchingProtocols			= 101;
	const Processing					= 102;

	const OK							= 200;
	const Created						= 201;
	const Accepted						= 202;
	const NonAuthoritativeInformation	= 203;
	const NoContent						= 204;
	const ResetContent					= 205;
	const PartialContent				= 206;
	const MultiStatus					= 207;
	const AlreadyReported				= 208;
	const IMUsed						= 226;

	const MultipleChoices				= 300;
	const MovedPermanently				= 301;
	const Found							= 302;
	const SeeOther						= 303;
	const NotModified					= 304;
	const UseProxy						= 305;
	const Unused						= 306;
	const TemporaryRedirect				= 307;
	const PermanentRedirect				= 308;

	const BadRequest					= 400;
	const Unauthorized					= 401;
	const PaymentRequired				= 402;
	const Forbidden						= 403;
	const NotFound						= 404;
	const MethodNotAllowed				= 405;
	const NotAcceptable					= 406;
	const ProxyAuthenticationRequired	= 407;
	const RequestTimeout				= 408;
	const Conflict						= 409;
	const Gone							= 410;
	const LengthRequired				= 411;
	const PreconditionFailed			= 412;
	const PayloadTooLarge				= 413;
	const URITooLong					= 414;
	const UnsupportedMediaType			= 415;
	const RangeNotSatisfiable			= 416;
	const ExpectationFailed				= 417;

	const MisdirectedRequest			= 421;
	const UnprocessableEntity			= 422;
	const Locked						= 423;
	const FailedDependency				= 424;
	const UpgradeRequired				= 426;
	const PreconditionRequired			= 428;
	const TooManyRequests				= 429;
	const RequestHeaderFieldsTooLarge	= 431;
	const UnavailableForLegalReasons	= 451;

	const InternalServerError			= 500;
	const NotImplemented				= 501;
	const BadGateway					= 502;
	const ServiceUnavailable			= 503;
	const GatewayTimeout				= 504;
	const HTTPVersionNotSupported		= 505;
	const VariantAlsoNegotiates			= 506;
	const InsufficientStorage			= 507;
	const LoopDetected					= 508;
	const NotExtended					= 510;
    const NetworkAuthenticationRequired	= 511;

    // Public Methods

	/** Returns parsed information from the headers of the current request.
     *
	 * @return array the parsed information.
	 */

	public static function RequestInformation() {
		static $translationTable = array(
			'REQUEST_METHOD'                => 'method',
			'QUERY_STRING'                  => 'parameters',
			'REMOTE_ADDR'                   => 'address',
			'REMOTE_USER'                   => 'user',
			'REQUEST_URI'                   => 'path',
			'HTTP_X_HTTP_METHOD_OVERRIDE'   => 'method_override',
			'HTTP_X_REQUESTED_WITH'         => 'requested_with',
			'CONTENT_TYPE'					=> 'content_type',
		);

		if (!self::$requestInformation) {
			self::$requestInformation = array(
				'Root-Url' => Core::RootURL(),
			);

			foreach ($_SERVER as $currentKey => $currentValue) {
				if (isset($translationTable[$currentKey])) {
					$currentKey = $translationTable[$currentKey];
				} else if (substr($currentKey, 0, 5) == 'HTTP_') {
					$currentKey = strtolower(substr($currentKey, 5));
				} else {
					continue;
				}

				self::$requestInformation[implode('-', array_map('ucfirst', explode('_', $currentKey)))] = $currentValue;
			}

			ksort(self::$requestInformation);
		}

		return self::$requestInformation;
	}

    /** Sets HTTP headers for the specified status code.
     *
     * @param int $statusCode the HTTP status code to be sent to the client.
     *
     * @return int the requested status code or HTTP::InternalServerError if the specified code is invalid.
     */

	public static function Status(int $statusCode) {
		static $statusMessages = array(
			HTTP::Continue => 'Continue',
			HTTP::SwitchingProtocols => 'Switching Protocols',
			HTTP::Processing => 'Processing',
			HTTP::OK => 'OK',
			HTTP::Created => 'Created',
			HTTP::Accepted => 'Accepted',
			HTTP::NonAuthoritativeInformation => 'Non-Authoritative Information',
			HTTP::NoContent => 'No Content',
			HTTP::ResetContent => 'Reset Content',
			HTTP::PartialContent => 'Partial Content',
			HTTP::MultiStatus => 'Multi-Status',
			HTTP::AlreadyReported => 'Already Reported',
			HTTP::IMUsed => 'IM Used',
			HTTP::MultipleChoices => 'Multiple Choices',
			HTTP::MovedPermanently => 'Moved Permanently',
			HTTP::Found => 'Found',
			HTTP::SeeOther => 'See Other',
			HTTP::NotModified => 'Not Modified',
			HTTP::UseProxy => 'Use Proxy',
			HTTP::Unused => '(Unused)',
			HTTP::TemporaryRedirect => 'Temporary Redirect',
			HTTP::PermanentRedirect => 'Permanent Redirect',
			HTTP::BadRequest => 'Bad Request',
			HTTP::Unauthorized => 'Unauthorized',
			HTTP::PaymentRequired => 'Payment Required',
			HTTP::Forbidden => 'Forbidden',
			HTTP::NotFound => 'Not Found',
			HTTP::MethodNotAllowed => 'Method Not Allowed',
			HTTP::NotAcceptable => 'Not Acceptable',
			HTTP::ProxyAuthenticationRequired => 'Proxy Authentication Required',
			HTTP::RequestTimeout => 'Request Timeout',
			HTTP::Conflict => 'Conflict',
			HTTP::Gone => 'Gone',
			HTTP::LengthRequired => 'Length Required',
			HTTP::PreconditionFailed => 'Precondition Failed',
			HTTP::PayloadTooLarge => 'Payload Too Large',
			HTTP::URITooLong => 'URI Too Long',
			HTTP::UnsupportedMediaType => 'Unsupported Media Type',
			HTTP::RangeNotSatisfiable => 'Range Not Satisfiable',
			HTTP::ExpectationFailed => 'Expectation Failed',
			HTTP::MisdirectedRequest => 'Misdirected Request',
			HTTP::UnprocessableEntity => 'Unprocessable Entity',
			HTTP::Locked => 'Locked',
			HTTP::FailedDependency => 'Failed Dependency',
			HTTP::UpgradeRequired => 'Upgrade Required',
			HTTP::PreconditionRequired => 'Precondition Required',
			HTTP::TooManyRequests => 'Too Many Requests',
			HTTP::RequestHeaderFieldsTooLarge => 'Request Header Fields Too Large',
			HTTP::UnavailableForLegalReasons => 'Unavailable For Legal Reasons',
			HTTP::InternalServerError => 'Internal Server Error',
			HTTP::NotImplemented => 'Not Implemented',
			HTTP::BadGateway => 'Bad Gateway',
			HTTP::ServiceUnavailable => 'Service Unavailable',
			HTTP::GatewayTimeout => 'Gateway Timeout',
			HTTP::HTTPVersionNotSupported => 'HTTP Version Not Supported',
			HTTP::VariantAlsoNegotiates => 'Variant Also Negotiates',
			HTTP::InsufficientStorage => 'Insufficient Storage',
			HTTP::LoopDetected => 'Loop Detected',
			HTTP::NotExtended => 'Not Extended',
			HTTP::NetworkAuthenticationRequired => 'Network Authentication Required',
		);

		if (!isset($statusMessages[$statusCode])) {
			$statusCode = HTTP::InternalServerError;
		}

		header('HTTP/1.1 '.$statusCode.' '.$statusMessages[$statusCode], true, $statusCode);
		return $statusCode;
    }

	/** Configures dynamic content headers.
	 *
	 * If the headers were already sent to the client the execution is halted with an error HTTP::InternalServerError.
	 * It is recomended to use Core::CleanOuput() before calling this method.
	 *
	 * @param string $contentType the MIME type of the content that will be sent after the headers.
	 * @param string $attachmentName (optional) a file name to be used in case of downloadable files (forces the download of the content if used).
	 * @param int $contentLength (optional) the size of the content that will be sent.
     * @param array $extraHeaders (optional) extra headers to be sent along with the dynamic content ones.
     *
	 * @see Core::CleanOutput()
	 */

	public static function DynamicHeaders(string $contentType, string $attachmentName = '', int $contentLength = 0, array $extraHeaders = array()) {
		if (headers_sent()) {
			Core::Halt(HTTP::InternalServerError, self::Tag, 'Headers already sent.');
		}

		header('Content-type: '.$contentType);
		header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
		header('Last-Modified: '.gmdate('r'));
		header('Cache-Control: no-store, no-cache, must-revalidate');
		header('Cache-Control: post-check=0, pre-check=0', false);
		header('Pragma: no-cache');

		if ($attachmentName != '') {
			header('Content-Disposition: attachment; filename='.urlencode($attachmentName));
		}

		if ($contentLength > 0) {
    		header('Content-Length: '.$contentLength);
		}

		foreach ($extraHeaders as $headerName => $headerValue) {
			header($headerName.': '.$headerValue);
		}
    }

    // Private Members

    private static $requestInformation = null;
}
