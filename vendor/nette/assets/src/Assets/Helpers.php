<?php

declare(strict_types=1);

namespace Nette\Assets;

use Nette;


/**
 * Static helper class providing utility functions for working with assets.
 */
final class Helpers
{
	use Nette\StaticClass;

	private const ExtensionToMime = [
		'avif' => 'image/avif', 'gif' => 'image/gif', 'ico' => 'image/vnd.microsoft.icon', 'jpeg' => 'image/jpeg', 'jpg' => 'image/jpeg', 'png' => 'image/png', 'svg' => 'image/svg+xml', 'webp' => 'image/webp',
		'js' => 'application/javascript', 'mjs' => 'application/javascript',
		'css' => 'text/css',
		'aac' => 'audio/aac', 'flac' => 'audio/flac', 'm4a' => 'audio/mp4', 'mp3' => 'audio/mpeg', 'ogg' => 'audio/ogg', 'wav' => 'audio/wav',
		'avi' => 'video/x-msvideo', 'mkv' => 'video/x-matroska', 'mov' => 'video/quicktime', 'mp4' => 'video/mp4', 'ogv' => 'video/ogg', 'webm' => 'video/webm',
		'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf',
	];


	public static function createAssetFromUrl(string $url, ?string $path = null, array $args = []): Asset
	{
		$args['url'] = $url;
		$args['file'] = $path;
		$mime = (string) $args['mimeType'] ??= self::guessMimeTypeFromExtension($url);
		$class = match (true) {
			$mime === 'application/javascript' => ScriptAsset::class,
			$mime === 'text/css' => StyleAsset::class,
			str_starts_with($mime, 'image/') => ImageAsset::class,
			str_starts_with($mime, 'audio/') => AudioAsset::class,
			str_starts_with($mime, 'video/') => VideoAsset::class,
			$mime === 'font/woff' || $mime === 'font/woff2' || $mime === 'font/ttf' => FontAsset::class,
			default => GenericAsset::class,
		};
		return new $class(...$args);
	}


	public static function guessMimeTypeFromExtension(string $url): ?string
	{
		return preg_match('~\.([a-z0-9]{1,5})([?#]|$)~i', $url, $m)
			? self::ExtensionToMime[strtolower($m[1])] ?? null
			: null;
	}


	/**
	 * Splits a potentially qualified reference 'mapper:reference' into a [mapper, reference] array.
	 * @return array{?string, string}
	 */
	public static function parseReference(string $qualifiedRef): array
	{
		$parts = explode(':', $qualifiedRef, 2);
		return count($parts) === 1
			? [null, $parts[0]]
			: [$parts[0], $parts[1]];
	}


	/**
	 * Validates an array of options against allowed optional and required keys.
	 * @throws \InvalidArgumentException if there are unsupported or missing options
	 */
	public static function checkOptions(array $array, array $optional = [], array $required = []): void
	{
		if ($keys = array_diff(array_keys($array), $optional, $required)) {
			throw new \InvalidArgumentException('Unsupported asset options: ' . implode(', ', $keys));
		}
		if ($keys = array_diff($required, array_keys($array))) {
			throw new \InvalidArgumentException('Missing asset options: ' . implode(', ', $keys));
		}
	}


	/**
	 * Estimates the duration (in seconds) of an MP3 file, assuming constant bitrate (CBR).
	 * @throws \RuntimeException If the file cannot be opened, MP3 sync bits aren't found, or the bitrate is invalid/unsupported.
	 */
	public static function guessMP3Duration(string $path): float
	{
		if (
			($header = @file_get_contents($path, length: 10000)) === false // @ - file may not exist
			|| ($fileSize = @filesize($path)) === false
		) {
			throw new \RuntimeException(sprintf("Failed to open file '%s'. %s", $path, Nette\Utils\Helpers::getLastError()));
		}

		$frameOffset = strpos($header, "\xFF\xFB"); // 0xFB indicates MPEG Version 1, Layer III, no protection bit.
		if ($frameOffset === false) {
			throw new \RuntimeException('Failed to find MP3 frame sync bits.');
		}

		$frameHeader = substr($header, $frameOffset, 4);
		$headerBits = unpack('N', $frameHeader)[1];
		$bitrateIndex = ($headerBits >> 12) & 0xF;
		$bitrate = [null, 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320][$bitrateIndex] ?? null;
		if ($bitrate === null) {
			throw new \RuntimeException('Invalid or unsupported bitrate index.');
		}

		return $fileSize * 8 / $bitrate / 1000;
	}


	public static function detectDevServer(string $infoFile): ?string
	{
		if (!is_file($infoFile)) {
			return null;
		}

		$info = json_decode(file_get_contents($infoFile), associative: true);
		return isset($info['devServer'])
			&& ($url = parse_url($info['devServer']))
			&& self::isPortOpen($url['host'], $url['port'])
			? $info['devServer']
			: null;
	}


	public static function isPortOpen(string $host, int $port): bool
	{
		$fp = @fsockopen($host, $port, timeout: 0);
		if (!$fp) {
			return false;
		}

		stream_set_blocking($fp, false);
		$read = [];
		$write = [$fp];
		$except = [$fp];
		$ready = stream_select($read, $write, $except, 0, 0);
		fclose($fp);
		return $ready > 0;
	}
}
