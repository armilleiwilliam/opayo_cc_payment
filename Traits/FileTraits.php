<?php
namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

trait FileTraits
{
    /**
     * Convert import file to array
     */
    public function fileContentsToArray($filename)
    {
        if ($delimiter = $this->getFileDelimiter($filename)) {
            $fp = fopen($filename, 'r') or die("Error opening $filename\n");

            $this->fileContents = [];
            $counter = 1;
            while ($buffer = fgetcsv($fp, 0, ',', '"')) {
                if ($this->isValidRow($buffer)) {
                    //dump($buffer);
                    //die();
                    $this->fileContents[$buffer[2]] = $buffer;
                } else {
                    //echo "Error line $counter, incorrect column count<br>";
                    //dump($buffer);
                    $this->file_line_errors++;
                }
                $counter++;
            }
            fclose($fp);
        }

    }

    /**
     * Probably pointless but looks for delimiter in valid file lines
     */
    public function getFileDelimiter($filename)
    {
        $fp = fopen($filename, 'r') or die("Error opening $filename\n");

        $split = false;
        $delimiters = [",", "\t"];
        while ($buffer = fgets($fp)) {
            foreach ($delimiters as $split) {
                $tok = explode($split, $buffer);
                if (count($tok) > 1) {
                    fclose($fp);
                    return $split;
                    $delimiter = $split;
                }
            }
            break;
        }
        fclose($fp);
        return $split;
    }

    /**
     * Convert XLS file to array
     */
    public function lookupFileToArray($filename)
    {
        $fp = fopen($filename, 'r') or die("Error opening $filename\n");

        $this->lookupRow = [];
        while ($buffer = fgetcsv($fp, 0, ',', '"')) {
            $dates = explode('/', $buffer[0]);
            // check that row has the right number of columns, first is a valid date and the reference field is not blank

            if (count($dates) == 3 AND !empty($buffer[2])) {
                $tok = explode(' ', $buffer[2]);
                $last = end($tok);
                if (strstr($last, '-')) {
                    $tok = explode('-', $last);
                    $reference = end($tok);
                } else {
                    $reference = $last;
                }

                if (ctype_digit($reference)) {
                    $this->lookupRow[$reference] = $buffer;
                } else {
                    //dump($buffer);
                }
            }
        }
    }

    public function isValidRow($row) {
        // check date
        $dates = explode('/', $row[1]);
        if (count($dates) != 3) {
            return false;
        }

        // check other fields
        if (empty($row[2]) OR empty($row[7]) OR empty($row[8]) OR empty($row[9]) OR empty($row[10])) {
            return false;
        }
        return true;
    }

}
