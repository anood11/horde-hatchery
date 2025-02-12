--TEST--
File_PDF: Locale specific floats test
--FILE--
<?php

require_once dirname(__FILE__) . '/../PDF.php';
setlocale(LC_ALL, 'de_DE');

// Set up the pdf object.
$pdf = &File_PDF::factory(array('orientation' => 'P', 'format' => 'A4'));
// Start the document.
$pdf->open();
// Start a page.
$pdf->addPage();
// Set font to Courier 8 pt.
$pdf->setFont('Courier', '', 8);
// Text at x=100 and y=100.
$pdf->text(100, 100, 'Locale breakage in PHP 4.3.10');
// Print the generated file.
echo $pdf->getOutput();

?>
--EXPECTF--
%PDF-1.3
3 0 obj
<</Type /Page
/Parent 1 0 R
/Resources 2 0 R
/Contents 4 0 R>>
endobj
4 0 obj
<</Filter /FlateDecode /Length 91>>
stream
x�3R��2�35W(�r
Q�w3T��30PISp�Y뙘)��Z�+��(h��''�*$�&f'��*d�)x(���h*�d�t f�
endstream
endobj
1 0 obj
<</Type /Pages
/Kids [3 0 R ]
/Count 1
/MediaBox [0 0 595.28 841.89]
>>
endobj
5 0 obj
<</Type /Font
/BaseFont /Courier
/Subtype /Type1
/Encoding /WinAnsiEncoding
>>
endobj
2 0 obj
<</ProcSet [/PDF /Text /ImageB /ImageC /ImageI]
/Font <<
/F1 5 0 R
>>
>>
endobj
6 0 obj
<<
/Producer (Horde PDF)
/CreationDate (D:%d)
>>
endobj
7 0 obj
<<
/Type /Catalog
/Pages 1 0 R
/OpenAction [3 0 R /FitH null]
/PageLayout /OneColumn
>>
endobj
xref
0 8
0000000000 65535 f 
0000000247 00000 n 
0000000428 00000 n 
0000000009 00000 n 
0000000087 00000 n 
0000000334 00000 n 
0000000516 00000 n 
0000000592 00000 n 
trailer
<<
/Size 8
/Root 7 0 R
/Info 6 0 R
>>
startxref
695
%%EOF
