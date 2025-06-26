<?php

declare(strict_types=1);

namespace Nette\Assets;

use Nette;
use function array_diff, array_keys, count, explode, file_get_contents, filesize, implode, json_decode, parse_url, preg_match, sprintf, strpos, strtolower, substr, unpack;


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


	/**
	 * Creates an Asset instance. The asset type is detected by 'mimeType' if provided in $args,
	 * otherwise is guessed from the file extension of $path or $url.
	 * @param  mixed[]  $args  parameters passed to the asset constructor
	 */
	public static function createAssetFromUrl(string $url, ?string $path = null, array $args = []): Asset
	{
		$args['url'] = $url;
		$args['file'] = $path;
		$argsMime = $args;
		$mimeType = (string) $argsMime['mimeType'] ??= self::guessMimeTypeFromExtension($path ?? $url);
		$primary = explode('/', $mimeType, 2)[0];
		return match (true) {
			$mimeType === 'application/javascript' => new ScriptAsset(...$args),
			$mimeType === 'text/css' => new StyleAsset(...$args),
			$primary === 'image' => new ImageAsset(...$args),
			$primary === 'audio' => new AudioAsset(...$argsMime),
			$primary === 'video' => new VideoAsset(...$argsMime),
			$primary === 'font' => new FontAsset(...$argsMime),
			default => new GenericAsset(...$argsMime),
		};
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
		return ($info = @file_get_contents($infoFile)) // @ file may not exists
			&& ($info = json_decode($info, associative: true))
			&& isset($info['devServer'])
			&& ($url = parse_url($info['devServer']))
			? $info['devServer']
			: null;
	}
}
