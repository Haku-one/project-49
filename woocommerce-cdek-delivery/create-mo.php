<?php
/**
 * Simple script to create .mo file from .po file
 */

function po_to_mo($po_file, $mo_file) {
    $po_content = file_get_contents($po_file);
    if (!$po_content) {
        return false;
    }
    
    $translations = array();
    $lines = explode("\n", $po_content);
    $msgid = '';
    $msgstr = '';
    $in_msgid = false;
    $in_msgstr = false;
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        if (strpos($line, 'msgid ') === 0) {
            if ($msgid && $msgstr) {
                $translations[$msgid] = $msgstr;
            }
            $msgid = substr($line, 7, -1);
            $msgstr = '';
            $in_msgid = true;
            $in_msgstr = false;
        } elseif (strpos($line, 'msgstr ') === 0) {
            $msgstr = substr($line, 8, -1);
            $in_msgid = false;
            $in_msgstr = true;
        } elseif ($line && $line[0] === '"') {
            $text = substr($line, 1, -1);
            if ($in_msgid) {
                $msgid .= $text;
            } elseif ($in_msgstr) {
                $msgstr .= $text;
            }
        }
    }
    
    if ($msgid && $msgstr) {
        $translations[$msgid] = $msgstr;
    }
    
    // Create simple .mo file content
    $mo_content = '';
    
    // MO file header
    $mo_content .= pack('V', 0x950412de); // Magic number
    $mo_content .= pack('V', 0); // Version
    $mo_content .= pack('V', count($translations)); // Number of strings
    $mo_content .= pack('V', 28); // Offset of table with original strings
    $mo_content .= pack('V', 28 + count($translations) * 8); // Offset of table with translation strings
    $mo_content .= pack('V', 0); // Size of hashing table
    $mo_content .= pack('V', 0); // Offset of hashing table
    
    $keys = '';
    $values = '';
    $key_offsets = array();
    $value_offsets = array();
    
    foreach ($translations as $key => $value) {
        $key_offsets[] = array(strlen($key), strlen($keys));
        $keys .= $key . "\0";
        
        $value_offsets[] = array(strlen($value), strlen($values));
        $values .= $value . "\0";
    }
    
    $key_table = '';
    foreach ($key_offsets as $offset) {
        $key_table .= pack('V', $offset[0]);
        $key_table .= pack('V', 28 + count($translations) * 16 + $offset[1]);
    }
    
    $value_table = '';
    foreach ($value_offsets as $offset) {
        $value_table .= pack('V', $offset[0]);
        $value_table .= pack('V', 28 + count($translations) * 16 + strlen($keys) + $offset[1]);
    }
    
    $mo_content .= $key_table . $value_table . $keys . $values;
    
    return file_put_contents($mo_file, $mo_content);
}

// Create .mo file
$po_file = __DIR__ . '/languages/woocommerce-cdek-delivery-ru_RU.po';
$mo_file = __DIR__ . '/languages/woocommerce-cdek-delivery-ru_RU.mo';

if (po_to_mo($po_file, $mo_file)) {
    echo "MO file created successfully: $mo_file\n";
} else {
    echo "Failed to create MO file\n";
}
?>