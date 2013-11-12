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
            ->addOption('dateStart', null, InputOption::VALUE_OPTIONAL, 'Start from this date', date('Y-m-d'))
            ->addOption('allSales', null, InputOption::VALUE_NONE, 'Include all sales, not only based on week 1 sales', null)
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $email = $input->getArgument('email');
        if (is_null($email)) {
            throw new \InvalidArgumentException("Please specify an email");
        }

        $websiteIds = $input->getOption('onlyIds');
        $dateStart  = new \DateTime($input->getOption('dateStart'));
        $allSales   = $input->getOption('allSales');

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
        $zippedFilename = array();
        foreach ($websites as $website) {
            $results = array();
            $maxDay = bcadd(bcmul($website['weeks'], 7, 0), 1, 0);
            for ($i = 1; $i <= $maxDay; $i++) {
                $dbModel->createTemporaryProductTableByDayInterval($website['name'] . '_p' . $i, $website['pq'], 'price' . $i, 'qty' . $i, $i - 1, $dateStart->format('Y-m-d'));
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
                        if (!is_null($product['qty' . $i]) && $product['qty' . $i] != '-1') {
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

            $csvFileName    = '/tmp/' . 'blackops_' . $website['name'] . '.csv';
            $csvFilePointer = fopen($csvFileName, 'w');

            $header = array(
                'Domain',
                'PID',
                'Product',
                'Brand',
                'Category Name',
                'SKU',
                'Color',
                'Size',
                'Image',
                'Site',
            );

            if ($allSales) {
                if (isset($website['event'])) {
                    for ($i = 1; $i <= count($results); $i++) {
                        $header = array_merge($header, array(
                            'Price last ' . $i . ' week',
                            'Sold last ' . $i . ' week',
                        ));
                    }
                    $header = array_merge($header, array(
                        'Event Name',
                        'Event Start',
                        'Event End',
                        'Fulfillment'
                    ));
                }
            } else {
                $header = array_merge($header, array('Price'));

                for ($i = 1; $i <= count($results); $i++) {
                    $header = array_merge($header, array(
                        'Sold last ' . $i . ' week',
                    ));
                }

                if (isset($website['event'])) {
                    $header = array_merge($header, array(
                        'Event Name',
                        'Event Start',
                        'Event End',
                        'Fulfillment'
                    ));
                }
            }

            fputcsv($csvFilePointer, $header);

            $productCount = 0;
            if ($allSales) {
                $productsList = array();
                foreach ($results as $week => $products) {
                    foreach ($products as $product) {
                        $pid = $product['pid'];
                        if (array_key_exists($pid, $productsList)) {
                            // update price and sold column when pid is found sold on a different week
                            if (isset($productSold[$week][$pid])) {
                                $productsList[$pid]['priceLast' . $week . 'Week'] = '$' . $productSold[$week][$pid]['price'];
                                $productsList[$pid]['soldLast' . $week . 'Week']  = $productSold[$week][$pid]['units'];
                            }
                        } else {
                            $prod['domain']        = $website['name'];
                            $prod['id']            = $pid;
                            $prod['name']          = trim($product['pName']);
                            $prod['brand']         = trim($product['brand']);
                            $prod['cat']           = trim($product['cat']);
                            $prod['sku']           = trim($product['sku']);
                            $prod['color']         = trim($product['color']);
                            $prod['size']          = trim($product['size']);
                            $prod['imageUrl']      = trim($product['image_url']);
                            $prod['site']          = trim($product['url']);


                            // initialise all price and sold columns
                            for ($j = 1; $j <= count($results); $j++) {
                                $prod['priceLast' . $j . 'Week'] = '$0.00';
                                $prod['soldLast' . $j . 'Week']  = 0;
                            }

                            // update the week column when first pid found
                            if (isset($productSold[$week][$pid])) {
                                $prod['priceLast' . $week . 'Week'] = '$' . $productSold[$week][$pid]['price'];
                                $prod['soldLast' . $week . 'Week']  = $productSold[$week][$pid]['units'];
                            }

                            if (isset($website['event'])) {
                                $prod['eventName']   = trim($product['eventName']);
                                $prod['startDate']   = trim($product['startDate']);
                                $prod['endDate']     = trim($product['endDate']);
                                $prod['fulfillment'] = trim($product['fulfillment_point']);
                            }

                            $productsList[$pid] = $prod;
                        }
                    }
                }

                foreach ($productsList as $pid => $prod) {
                    $productCount++;
                    // Stream to file
                    fputcsv($csvFilePointer, $prod);
                }
            } else {
                foreach ($results[1] as $product) {
                    $pid = $product['pid'];
                    if (isset($productSold[1][$pid]) && intval($productSold[1][$pid]) > 0) {
                        $prod['domain']        = $website['name'];
                        $prod['id']            = $pid;
                        $prod['name']          = trim($product['pName']);
                        $prod['brand']         = trim($product['brand']);
                        $prod['cat']           = trim($product['cat']);
                        $prod['sku']           = trim($product['sku']);
                        $prod['color']         = trim($product['color']);
                        $prod['size']          = trim($product['size']);
                        $prod['imageUrl']      = trim($product['image_url']);
                        $prod['site']          = trim($product['url']);
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

                        if (isset($website['event'])) {
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
            }

            fclose($csvFilePointer);

            if ($productCount) {
                $haveDataToSend = true;
                $zipped = $this->createZip($csvFileName);
                $output->writeln($zipped['filename']);
                $attachment = \Swift_Attachment::fromPath($zipped['filename'], 'application/zip');
                $message->attach($attachment);
                $zippedFilename[] = $zipped['filename'];
            }
            unlink($csvFileName);
            unset($results);
        }

        if ($haveDataToSend) {
            $mailer->send($message);
            foreach ($zippedFilename as $file) {
                unlink($file);
            }
        }
    }

    private function getTableNames($activeWebsites)
    {
        $websites = array();
        foreach ($activeWebsites as $web) {
            $temp = explode('.', $web['url']);
            $table = array(
                'id'      => $web['id'],
                'name'    => str_replace('-', '', $temp[0]),
                'product' => 'product_' . str_replace('-', '', $temp[0]),
                'pq'      => 'pq_' . str_replace('-', '', $temp[0]),
                'weeks'   => $web['report_weeks'],
                'qtyDiff' => (intval($web['daily_scraping']) == 0) ? false : true,
            );
            if (intval($web['have_event']) == 1) {
                $table = array_merge($table, array(
                    'event'     => str_replace('-', '', $temp[0]) . '_event',
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