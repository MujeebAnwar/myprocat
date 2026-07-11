<?php
if(!function_exists('random_bytes'))
{
    function random_bytes($nBytes)
    {
        return openssl_random_pseudo_bytes($nBytes);
    }
}
if(!function_exists('random_int'))
{
    function random_int($min,$max)
    {
        $rangeSize = $max-$min;
        if(!is_int($rangeSize) || $rangeSize < 2)
        {
            throw new InvalidArgumentException('Invalid numeric range');
        }
        $tmp = $rangeSize;
        $bits = 0;
        $bytes = 0;
        $mask = 0;
        while ($tmp > 0) 
        {
            if ($bits % 8 === 0) {

                ++$bytes;
            }
            ++$bits;
            $tmp >>= 1;
            $mask = $mask << 1 | 1;
        }
        do
        {
            $value = 0;
            $valueString = random_bytes($bytes);
            for($i = 0; $i < $bytes;$i++)
            {
                $value |= ord($valueString[$i]) << (8*$i);
            }
            $value &= $mask;
        } while (!is_int($value) || $value > $rangeSize);
        return $min + $value;
    }
}
if(!function_exists('random_string'))
{
    function random_string($length = 26, $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567')
    {
        if ($length < 1) {
            throw new InvalidArgumentException('Length must be a positive integer');
        }
        $str = '';
        $alphamax = strlen($alphabet) - 1;
        if ($alphamax < 1) {
            throw new InvalidArgumentException('Invalid alphabet');
        }
        for ($i = 0; $i < $length; ++$i) {
            $str .= $alphabet[random_int(0, $alphamax)];
        }
        return $str;
    }
}
?>