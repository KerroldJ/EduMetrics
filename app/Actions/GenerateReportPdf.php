<?php

namespace App\Actions;

use Spatie\Browsershot\Browsershot;

class GenerateReportPdf
{
    public function handle(string $html): string
    {
        $browser = Browsershot::html($html)
            ->setNodeModulePath(base_path('node_modules'))
            ->format('A4')->margins(14, 12, 18, 12)->showBackground()
            ->disableJavascript()->timeout(60)
            ->showBrowserHeaderAndFooter()->headerHtml('<div></div>')
            ->footerHtml('<div style="width:100%;padding:0 12mm;font:9px Arial;color:#64748b;text-align:right">EduMetric · Page <span class="pageNumber"></span> of <span class="totalPages"></span></div>');

        if ($chrome = config('browsershot.chrome_path')) {
            $browser->setChromePath($chrome);
        }
        if ($node = config('browsershot.node_binary')) {
            $browser->setNodeBinary($node);
        }
        if (config('browsershot.no_sandbox')) {
            $browser->noSandbox();
        }

        return $browser->pdf();
    }
}
