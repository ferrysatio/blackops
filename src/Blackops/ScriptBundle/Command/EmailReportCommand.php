<?php

namespace Blackops\ScriptBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand,
    Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Output\OutputInterface;

class EmailReportCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('email:report:sales')
            ->setDescription('Email weekly report for web sales')
            ->addArgument('email', InputArgument::REQUIRED, 'Email address to send the report to.')
            ->addOption('includeIds', null, InputOption::VALUE_OPTIONAL, 'Include these website ids (comma separated)')
            ->addOption('excludeIds', null, InputOption::VALUE_OPTIONAL, 'Exclude these website ids (comma separated)')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $email = $input->getArgument('email');

        $includeIds = $input->getOption('includeIds');
        if (!is_null($includeIds)) {
            $includeIds = explode(',', $includeIds);
        }

        $excludeIds = $input->getOption('excludeIds');
        if (!is_null($excludeIds)) {
            $excludeIds = explode(',', $excludeIds);
        }

        if (is_null($email)) {
            throw new \InvalidArgumentException("Please specify an email");
        }

        $getQtyDiff = true;
        if (!is_null($includeIds) && in_array('13', $includeIds)) {
            $getQtyDiff = true;
        }

        if (!is_null($includeIds) && in_array('2', $includeIds)) {
            $getQtyDiff = false;
        }

        /** @var \Blackops\ScriptBundle\Model\DbModel $dbModel */
        $dbModel = $this->getContainer()->get('blackops.script.dbmodel');

        $maxDay = 22;
        if (!is_null($includeIds) && in_array('13', $includeIds)) {
            $maxDay = 8;
        }

        for ($i = 1; $i <= $maxDay; $i++) {
            $dbModel->createTemporaryProductTableByDayInterval('p' . $i, 'price' . $i, 'qty' . $i, $i - 1);
        }

        if ((!is_null($excludeIds) && in_array('13', $excludeIds)) || (!is_null($includeIds) && in_array('2', $includeIds))) {
            $productsListLastWeek = $dbModel->getQtyDifferenceListLastWeek($includeIds, $excludeIds, $getQtyDiff);
            $productsSoldLastWeek = array();
            foreach ($productsListLastWeek as $product) {
                $pid = $product['pid'];
                $qtyPrev = 0;
                $qtyNow = 0;
                $totalSold = 0;
                for ($i = 15; $i >= 8; $i--) {
                    if (!is_null($product['qty' . $i])) {
                        $qtyPrev = $qtyNow;
                        $qtyNow  = $product['qty' . $i];
                        if (($qtyPrev - $qtyNow) > 0) {
                            $totalSold += ($qtyPrev - $qtyNow);
                        }
                    }
                }
                if ($totalSold || (!is_null($includeIds) && in_array('13', $includeIds))) {
                    $productsSoldLastWeek[$pid] = $totalSold;
                }
            }

            $productsList2WeeksAgo = $dbModel->getQtyDifferenceList2WeeksAgo($includeIds, $excludeIds, $getQtyDiff);
            $productsSold2WeeksAgo = array();
            foreach ($productsList2WeeksAgo as $product) {
                $pid = $product['pid'];
                $qtyPrev = 0;
                $qtyNow = 0;
                $totalSold = 0;
                for ($i = 22; $i >= 15; $i--) {
                    if (!is_null($product['qty' . $i])) {
                        $qtyPrev = $qtyNow;
                        $qtyNow  = $product['qty' . $i];
                        if (($qtyPrev - $qtyNow) > 0) {
                            $totalSold += ($qtyPrev - $qtyNow);
                        }
                    }
                }
                if ($totalSold || (!is_null($includeIds) && in_array('13', $includeIds))) {
                    $productsSold2WeeksAgo[$pid] = $totalSold;
                }
            }
        }

        $csvFileName    = '/tmp/' . uniqid('blackops_sales_report' . '_' . date('Y-m-d_H-i-s') . '_') . '.csv';
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
            'Sold this week',
        );

        if (!is_null($excludeIds) && in_array('13', $excludeIds)) {
            $header = array_merge($header, array(
                'Sold last week',
                'Sold 2 weeks ago'
            ));
        }

        if (!is_null($includeIds) && in_array('13', $includeIds)) {
            $header = array_merge($header, array(
                'Event Name',
                'Event Start',
                'Event End',
                'Fulfillment'
            ));
        }

        fputcsv($csvFilePointer, $header);

        $productCount = 0;
        $qtyDiffListCurrentWeek = $dbModel->getQtyDifferenceListCurrentWeek($includeIds, $excludeIds, $getQtyDiff);
        foreach ($qtyDiffListCurrentWeek as $qtyDiff) {
            $pid = $qtyDiff['pid'];
            $qtyPrev = 0;
            $qtyNow = 0;
            $lastPrice = 0;
            $totalSold = 0;
            $prod = array();
            for ($i = 8; $i >= 1; $i--) {
                if (!is_null($qtyDiff['qty' . $i])) {
                    $qtyPrev = $qtyNow;
                    $qtyNow  = $qtyDiff['qty' . $i];
                    $lastPrice = $qtyDiff['price' . $i];
                    if (($qtyPrev - $qtyNow) > 0) {
                        $totalSold += ($qtyPrev - $qtyNow);
                    }
                }
            }
            if ($totalSold || (!is_null($includeIds) && in_array('13', $includeIds))) {
                $prod['id']           = $pid;
                $prod['name']         = trim($qtyDiff['pName']);
                $prod['brand']        = trim($qtyDiff['brand']);
                $prod['cat']          = trim($qtyDiff['cat']);
                $prod['sku']          = trim($qtyDiff['sku']);
                $prod['color']        = trim($qtyDiff['color']);
                $prod['size']         = trim($qtyDiff['size']);
                $prod['imageUrl']     = trim($qtyDiff['image_url']);
                $prod['domain']       = trim($qtyDiff['url']);
                $prod['price']        = '$' . $lastPrice;
                $prod['soldThisWeek'] = $totalSold;
                if ((!is_null($excludeIds) && in_array('13', $excludeIds)) || (!is_null($includeIds) && in_array('2', $includeIds))) {
                    if (isset($productsSoldLastWeek[$pid])) {
                        $prod['soldLastWeek'] = $productsSoldLastWeek[$pid];
                    } else {
                        $prod['soldLastWeek'] = 0;
                    }
                    if (isset($productsSold2WeeksAgo[$pid])) {
                        $prod['sold2WeeksAgo'] = $productsSold2WeeksAgo[$pid];
                    } else {
                        $prod['sold2WeeksAgo'] = 0;
                    }
                }
                if (!is_null($includeIds) && in_array('13', $includeIds)) {
                    $prod['eventName']   = trim($qtyDiff['eventName']);
                    $prod['startDate']   = trim($qtyDiff['startDate']);
                    $prod['endDate']     = trim($qtyDiff['endDate']);
                    $prod['fulfillment'] = trim($qtyDiff['fulfillment_point']);
                }
                $productCount++;

                // Stream to file
                fputcsv($csvFilePointer, $prod);
            }
        }

        fclose($csvFilePointer);

        if ($productCount) {
            $mailer     = $this->getContainer()->get('mailer');
            $now = new \DateTime();
            $attachment = \Swift_Attachment::fromPath($csvFileName, 'text/csv');
            $emailSubject = 'Blackops Weekly Sales Report ' . $now->format("Y-m-d H:i");
            $output->writeln($csvFileName);
            $message = \Swift_Message::newInstance()
                ->setSubject($emailSubject)
                ->setFrom('noreply@catchoftheday.com.au')
                ->setTo($email);
            $message->attach($attachment);
            $mailer->send($message);
            //unlink($csvFileName);
        }
    }
}