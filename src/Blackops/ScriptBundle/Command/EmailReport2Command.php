<?php

namespace Blackops\ScriptBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand,
    Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Output\OutputInterface;

class EmailReport2Command extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('email:report:salesV2')
            ->setDescription('Email weekly report for web sales')
            ->addArgument('email', InputArgument::REQUIRED, 'Email address to send the report to.')
            ->addOption('onlyIds', null, InputOption::VALUE_OPTIONAL, 'Only these website ids (comma separated)', null)
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $email = $input->getArgument('email');
        if (is_null($email)) {
            throw new \InvalidArgumentException("Please specify an email");
        }

        $websiteIds = $input->getOption('onlyIds');

        /** @var \Blackops\ScriptBundle\Model\DbModel2 $dbModel */
        $dbModel  = $this->getContainer()->get('blackops.script.dbmodel2');
        $websites = $this->getTableNames($dbModel->getActiveWebsites($websiteIds));

        $mailer       = $this->getContainer()->get('mailer');
        $now          = new \DateTime();
        $emailSubject = 'Blackops Weekly Sales Report ' . $now->format("Y-m-d H:i");
        $message      = \Swift_Message::newInstance()
                            ->setSubject($emailSubject)
                            ->setFrom('noreply@catchoftheday.com.au')
                            ->setTo($email);

        $haveDataToSend = false;
        foreach ($websites as $website) {
            $results = array();
            $maxDay = bcadd(bcmul($website['weeks'], 7, 0), 1, 0);
            for ($i = 1; $i <= $maxDay; $i++) {
                $dbModel->createTemporaryProductTableByDayInterval($website['name'] . '_p' . $i, $website['pq'], 'price' . $i, 'qty' . $i, $i - 1);
            }

            for ($i = 1; $i <= $website['weeks']; $i++) {
                $dayFrom = bcsub(bcmul($i, 7, 0), 6, 0);
                $dayTo   = bcadd(bcmul($i, 7, 0), 1, 0);

                $results[$i] = $dbModel->getWeeklyData($website, $dayFrom, $dayTo, $website['qtyDiff']);
            }

            foreach ($results as $week => $products) {
                $productSold[$week] = array();
                foreach ($products as $product) {
                    $pid       = $product['pid'];
                    $qtyPrev   = 0;
                    $qtyNow    = 0;
                    $lastPrice = 0;
                    $totalSold = 0;
                    $dayFrom   = bcadd(bcmul($week, 7, 0), 1, 0);
                    $dayTo     = bcsub(bcmul($week, 7, 0), 6, 0);
                    for ($i = $dayFrom; $i >= $dayTo; $i--) {
                        if (!is_null($product['qty' . $i])) {
                            $qtyPrev   = $qtyNow;
                            $qtyNow    = $product['qty' . $i];

                            if ($i == $dayFrom) {
                                $qtyPrev = $qtyNow = $product['qty' . $i];
                            }

                            $lastPrice = $product['price' . $i];
                            if (($qtyPrev - $qtyNow) > 0) {
                                $totalSold += ($qtyPrev - $qtyNow);
                            }
                        }
                    }
                    if ($totalSold) {
                        $productSold[$week][$pid] = array(
                            'units' => $totalSold,
                            'price' => $lastPrice,
                        );
                    }
                }
            }

            $csvFileName    = '/tmp/' . uniqid('blackops_' . $website['name'] . '_' . date('Y-m-d_H-i-s') . '_') . '.csv';
            $csvFilePointer = fopen($csvFileName, 'w');

            $header = array(
                'PID',
                'Product',
                'Brand',
                'Category Name',
                'SKU',
                'Color',
                'Size',
                'Image',
                'Site',
                'Price',
            );

            for ($i = 1; $i <= count($results); $i++) {
                $header = array_merge($header, array(
                    'Sold last ' . $i . ' week',
                ));
            }

            if (isset($website['composite']) && isset($website['event'])) {
                $header = array_merge($header, array(
                    'Event Name',
                    'Event Start',
                    'Event End',
                    'Fulfillment'
                ));
            }

            fputcsv($csvFilePointer, $header);

            $productCount = 0;
            foreach ($results[1] as $product) {
                $pid = $product['pid'];
                if (isset($productSold[1][$pid]) && intval($productSold[1][$pid]) > 0) {
                    $prod['id']            = $pid;
                    $prod['name']          = trim($product['pName']);
                    $prod['brand']         = trim($product['brand']);
                    $prod['cat']           = trim($product['cat']);
                    $prod['sku']           = trim($product['sku']);
                    $prod['color']         = trim($product['color']);
                    $prod['size']          = trim($product['size']);
                    $prod['imageUrl']      = trim($product['image_url']);
                    $prod['domain']        = trim($product['url']);
                    $prod['price']         = '$' . $productSold[1][$pid]['price'];
                    $prod['soldLast1Week'] = $productSold[1][$pid]['units'];

                    if (count($results) > 1) {
                        for ($i = 2; $i <= count($results); $i++) {
                            if (isset($productSold[$i][$pid])) {
                                $prod['soldLast' . $i . 'Week'] = $productSold[$i][$pid]['units'];
                            } else {
                                $prod['soldLast' . $i . 'Week'] = 0;
                            }
                        }
                    }

                    if (isset($website['composite']) && isset($website['event'])) {
                        $prod['eventName']   = trim($product['eventName']);
                        $prod['startDate']   = trim($product['startDate']);
                        $prod['endDate']     = trim($product['endDate']);
                        $prod['fulfillment'] = trim($product['fulfillment_point']);
                    }
                    $productCount++;

                    // Stream to file
                    fputcsv($csvFilePointer, $prod);
                }
            }

            fclose($csvFilePointer);

            if ($productCount) {
                $haveDataToSend = true;
                $zipped = $this->createZip($csvFileName);
                $output->writeln($zipped['filename']);
                $attachment = \Swift_Attachment::fromPath($zipped['filename'], 'application/zip');
                $message->attach($attachment);
                //unlink($zipped['filename']);
                //unlink($csvFileName);
            }
        }

        if ($haveDataToSend) {
            $mailer->send($message);
        }
    }

    private function getTableNames($activeWebsites)
    {
        $websites = array();
        foreach ($activeWebsites as $web) {
            $temp = explode('.', $web['url']);
            $table = array(
                'id'      => $web['id'],
                'name'    => $temp[0],
                'product' => 'product_' . $temp[0],
                'pq'      => 'pq_' . $temp[0],
                'weeks'   => $web['report_weeks'],
                'qtyDiff' => (intval($web['daily_scraping']) == 0) ? false : true,
            );
            if (intval($web['have_event']) == 1) {
                $table = array_merge($table, array(
                    'composite' => $temp[0] . '_product',
                    'event'     => $temp[0] . '_event',
                ));
            }
            $websites[] = $table;
        }

        return $websites;
    }

    private function createZip($csvFilename)
    {
        $tempDir     = '/tmp/';
        $zipFilename = $tempDir . basename($csvFilename) . '.zip';

        $zippedCsv   = new \ZipArchive;
        $zippedCsv->open($zipFilename, \ZipArchive::OVERWRITE);
        $zippedCsv->addFile($csvFilename, basename($csvFilename));
        $zippedCsv->close();

        return array(
            'filename' => $zipFilename,
            'csv'      => $zippedCsv,
        );
    }
}