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
            ->addArgument('email', InputOption::VALUE_REQUIRED, 'Email address to send the report to.')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $email = $input->getArgument('email');

        if (is_null($email)) {
            throw new \InvalidArgumentException("Please specify an email");
        }

        /** @var \Blackops\ScriptBundle\Model\DbModel $dbModel */
        $dbModel = $this->getContainer()->get('blackops.script.dbmodel');

        for ($i = 1; $i <= 22; $i++) {
            $dbModel->createTemporaryProductTableByDayInterval('p' . $i, 'price' . $i, 'qty' . $i, $i);
        }

        $productsListLastWeek = $dbModel->getQtyDifferenceListLastWeek();
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
            if ($totalSold) {
                $productsSoldLastWeek[$pid] = $totalSold;
            }
        }

        $productsList2WeeksAgo = $dbModel->getQtyDifferenceList2WeeksAgo();
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
            if ($totalSold) {
                $productsSold2WeeksAgo[$pid] = $totalSold;
            }
        }

        $csvFileName    = '/tmp/' . uniqid('blackops_sales_report' . '_' . date('Y-m-d_H-i-s') . '_') . '.csv';
        $csvFilePointer = fopen($csvFileName, 'w');

        $header = array(
            'PID',
            'Product',
            'Category Name',
            'SKU',
            'Color',
            'Size',
            'Image',
            'Site',
            'Price',
            'Sold this week',
            'Sold last week',
            'Sold 2 weeks ago'
        );

        fputcsv($csvFilePointer, $header);

        $productCount = 0;
        $qtyDiffListCurrentWeek = $dbModel->getQtyDifferenceListCurrentWeek();
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
            if ($totalSold) {
                $prod['id']           = $pid;
                $prod['name']         = $qtyDiff['name'];
                $prod['cat']          = $qtyDiff['cat'];
                $prod['sku']          = $qtyDiff['sku'];
                $prod['color']        = $qtyDiff['color'];
                $prod['size']         = $qtyDiff['size'];
                $prod['imageUrl']     = $qtyDiff['image_url'];
                $prod['domain']       = $qtyDiff['url'];
                $prod['price']        = '$' . $lastPrice;
                $prod['soldThisWeek'] = $totalSold;
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

            $message = \Swift_Message::newInstance()
                ->setSubject($emailSubject)
                ->setFrom('noreply@catchoftheday.com.au')
                ->setTo($email);
            $message->attach($attachment);
            $mailer->send($message);
            unlink($csvFileName);
        }
    }
}