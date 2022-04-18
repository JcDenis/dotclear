<?php
/**
 * @note Dotclear\Process\Admin\Handler\CspReport
 * @brief Dotclear admin csp report endpoint
 *
 * From: https://github.com/nico3333fr/CSP-useful
 * Note: this script requires PHP ≥ 5.4.
 * Inspired from https://mathiasbynens.be/notes/csp-reports
 *
 * @ingroup  Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Handler;

use Dotclear\Process\Admin\Page\AbstractPage;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\File\Files;

class CspReport extends AbstractPage
{
    // not used but required
    protected function getPermissions(): string|null|false
    {
        return false;
    }

    public function getPagePrepend(): ?bool
    {
        // Dareboost wants it? Not a problem.
        header('X-Content-Type-Options: "nosniff"');

        // Specify admin CSP log file if necessary
        if (!defined('LOGFILE')) {
            define('LOGFILE', Path::real(dotclear()->config()->get('var_dir') . '/csp/csp_report.json', false));
        }

        // Get the raw POST data
        $data = file_get_contents('php://input');

        // Only continue if it’s valid JSON that is not just `null`, `0`, `false` or an
        // empty string, i.e. if it could be a CSP violation report.
        if ($data = json_decode($data, true)) {
            // get source-file and blocked-URI to perform some tests
            $source_file        = $data['csp-report']['source-file']        ?? '';
            $line_number        = $data['csp-report']['line-number']        ?? '';
            $blocked_uri        = $data['csp-report']['blocked-uri']        ?? '';
            $document_uri       = $data['csp-report']['document-uri']       ?? '';
            $violated_directive = $data['csp-report']['violated-directive'] ?? '';

            if (
                // avoid false positives notifications coming from Chrome extensions (Wappalyzer, MuteTab, etc.)
                // bug here https://code.google.com/p/chromium/issues/detail?id=524356
                !str_contains($source_file, 'chrome-extension://')

                // avoid false positives notifications coming from Safari extensions (diigo, evernote, etc.)
                 && !str_contains($source_file, 'safari-extension://')
                && !str_contains($blocked_uri, 'safari-extension://')

                // search engine extensions ?
                 && !str_contains($source_file, 'se-extension://')

                // added by browsers in webviews
                 && !str_contains($blocked_uri, 'webviewprogressproxy://')

                // Google Search App see for details https://github.com/nico3333fr/CSP-useful/commit/ecc8f9b0b379ae643bc754d2db33c8b47e185fd1
                 && !str_contains($blocked_uri, 'gsa://onpageload')
            ) {
                // Prepare report data (hash => info)
                $hash = hash('md5', $blocked_uri . $document_uri . $source_file . $line_number . $violated_directive);

                try {
                    // Check report dir (create it if necessary)
                    Files::makeDir(dirname(LOGFILE), true);

                    // Check if report is not already stored in log file
                    $contents = '';
                    if (file_exists(LOGFILE)) {
                        $contents = file_get_contents(LOGFILE);
                        if ('' != $contents) {
                            if (substr($contents, -1) == ',') {
                                // Remove final comma if present
                                $contents = substr($contents, 0, -1);
                            }
                            if ('' != $contents) {
                                $list = json_decode('[' . $contents . ']', true);
                                if (is_array($list)) {
                                    foreach ($list as $idx => $value) {
                                        if (isset($value['hash']) && $value['hash'] == $hash) {
                                            // Already stored, ignore
                                            return null;
                                        }
                                    }
                                }
                            }
                        }
                    }

                    // Add report to the file
                    if (!($fp = @fopen(LOGFILE, 'a'))) {
                        // Unable to open file, ignore
                        return null;
                    }

                    // Prettify the JSON-formatted data
                    $violation = array_merge(['hash' => $hash], $data['csp-report']);
                    $output    = json_encode($violation, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

                    // The file content will have to be enclosed in brackets [] before
                    // beeing decoded with json_decoded(<content>,true);
                    fprintf($fp, ('' != $contents ? ',' : '') . '%s', $output);
                } catch (\Exception) {
                    return null;
                }
            }
        }

        return null;
    }
}
