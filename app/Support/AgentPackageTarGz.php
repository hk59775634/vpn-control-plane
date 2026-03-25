<?php

namespace App\Support;

/**
 * 不依赖外部 tar 进程与 Symfony Process（避免 PHP disable_functions=proc_open 时安装失败）。
 */
final class AgentPackageTarGz
{
    /**
     * @throws \RuntimeException
     */
    public static function buildFromDirectory(string $srcDir, string $destTarGz): void
    {
        $real = realpath($srcDir);
        if ($real === false || ! is_dir($real)) {
            throw new \RuntimeException('Agent 源码目录无效');
        }
        $srcDir = rtrim($real, DIRECTORY_SEPARATOR);

        $gz = @gzopen($destTarGz, 'wb9');
        if ($gz === false) {
            throw new \RuntimeException('无法写入压缩包：'.$destTarGz);
        }

        try {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($srcDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($it as $file) {
                /** @var \SplFileInfo $file */
                if ($file->isDir()) {
                    continue;
                }
                if ($file->isLink()) {
                    continue;
                }
                if (! $file->isFile()) {
                    continue;
                }
                $full = $file->getRealPath();
                if ($full === false || ! str_starts_with($full, $srcDir.DIRECTORY_SEPARATOR)) {
                    continue;
                }
                $rel = substr($full, strlen($srcDir) + 1);
                $rel = str_replace('\\', '/', $rel);
                $size = $file->getSize();
                $mtime = $file->getMTime();
                gzwrite($gz, self::ustarHeader($rel, $size, 0o644, $mtime));

                $fh = fopen($full, 'rb');
                if ($fh === false) {
                    throw new \RuntimeException('无法读取：'.$full);
                }
                while (! feof($fh)) {
                    $chunk = fread($fh, 262144);
                    if ($chunk === false || $chunk === '') {
                        break;
                    }
                    gzwrite($gz, $chunk);
                }
                fclose($fh);

                $pad = (512 - ($size % 512)) % 512;
                if ($pad > 0) {
                    gzwrite($gz, str_repeat("\0", $pad));
                }
            }

            gzwrite($gz, str_repeat("\0", 1024));
        } finally {
            gzclose($gz);
        }
    }

    private static function ustarHeader(string $path, int $size, int $mode, int $mtime): string
    {
        if (strlen($path) > 100) {
            $parts = explode('/', $path);
            $name = array_pop($parts) ?: '';
            $prefix = implode('/', $parts);
            if (strlen($name) > 100 || strlen($prefix) > 155) {
                throw new \RuntimeException('路径过长（ustar 限制）：'.$path);
            }
        } else {
            $name = $path;
            $prefix = '';
        }

        $b = array_fill(0, 512, "\0");

        self::writeStr($b, 0, $name, 100);
        self::writeOctal($b, 100, $mode & 0777, 7);
        self::writeOctal($b, 108, 0, 7);
        self::writeOctal($b, 116, 0, 7);
        self::writeOctal($b, 124, $size, 11);
        self::writeOctal($b, 136, $mtime, 11);
        for ($i = 148; $i < 156; $i++) {
            $b[$i] = ' ';
        }
        $b[156] = '0';
        self::writeStr($b, 257, "ustar\0", 6);
        self::writeStr($b, 263, '00', 2);
        self::writeStr($b, 345, $prefix, 155);

        $block = implode('', $b);
        $sum = 0;
        for ($i = 0; $i < 512; $i++) {
            $sum += ord($block[$i]);
        }
        $chksum = sprintf('%06o', $sum)."\0 ";
        $b = str_split($block, 1);
        self::writeStr($b, 148, $chksum, 8);

        return implode('', $b);
    }

    /**
     * @param list<string> $b
     */
    private static function writeStr(array &$b, int $offset, string $value, int $maxLen): void
    {
        $len = min(strlen($value), $maxLen);
        for ($i = 0; $i < $len; $i++) {
            $b[$offset + $i] = $value[$i];
        }
    }

    /**
     * @param list<string> $b
     */
    private static function writeOctal(array &$b, int $offset, int $value, int $digits): void
    {
        $fmt = '%0'.$digits.'o';
        $s = sprintf($fmt, $value);
        if (strlen($s) > $digits) {
            $s = substr($s, -$digits);
        }
        $s .= "\0";
        self::writeStr($b, $offset, $s, $digits + 1);
    }
}
