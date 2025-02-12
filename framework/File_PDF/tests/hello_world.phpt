--TEST--
File_PDF: "Hello World!" test
--FILE--
<?php

require_once dirname(__FILE__) . '/../PDF.php';

// Set up the pdf object.
$pdf = &File_PDF::factory(array('orientation' => 'P', 'format' => 'A4'));
// Start the document.
$pdf->open();
// Activate compression.
$pdf->setCompression(true);
// Start a page.
$pdf->addPage();
// Set font to Courier 8 pt.
$pdf->setFont('Courier', '', 8);
// Text at x=100 and y=100.
$pdf->text(100, 100, 'First page');
// Set font size to 20 pt.
$pdf->setFontSize(20);
// Text at x=100 and y=200.
$pdf->text(100, 200, 'HELLO WORLD!');
// Add a new page.
$pdf->addPage();
// Set font to Arial bold italic 12 pt.
$pdf->setFont('Arial', 'BI', 12);
// Text at x=100 and y=200.
$pdf->text(100, 100, 'Second page');
// Print the generated file.
echo $pdf->getOutput();

// Set up the pdf object.
$pdf = &File_PDF::factory(array('orientation' => 'P', 'format' => 'A4'));
// Start the document.
$pdf->open();
// Deactivate compression.
$pdf->setCompression(false);
// Start a page.
$pdf->addPage();
// Set font to Courier 8 pt.
$pdf->setFont('Courier', '', 8);
// Text at x=100 and y=100.
$pdf->text(100, 100, 'First page');
// Set font size to 20 pt.
$pdf->setFontSize(20);
// Text at x=100 and y=200.
$pdf->text(100, 200, 'HELLO WORLD!');
// Add a new page.
$pdf->addPage();
// Flush page.
echo $pdf->flush();
// Set font to Arial bold italic 12 pt.
$pdf->setFont('Arial', 'BI', 12);
// Text at x=100 and y=200.
$pdf->text(100, 100, 'Second page');
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
<</Filter /FlateDecode /Length 100>>
stream
x�3R��2�35W(�r
Q�w3T��30PISp�Y뙘)��Z�+��(h�e�($��j*�dA��4`�idn�gi���������� ?�W
endstream
endobj
5 0 obj
<</Type /Page
/Parent 1 0 R
/Resources 2 0 R
/Contents 6 0 R>>
endobj
6 0 obj
<</Filter /FlateDecode /Length 80>>
stream
x�3R��2�35W(�r
Q�w3T02�30PISp�)�Y뙘)��Z�+��(h�&��($��j*�d�� S�k
endstream
endobj
1 0 obj
<</Type /Pages
/Kids [3 0 R 5 0 R ]
/Count 2
/MediaBox [0 0 595.28 841.89]
>>
endobj
7 0 obj
<</Type /Font
/BaseFont /Courier
/Subtype /Type1
/Encoding /WinAnsiEncoding
>>
endobj
8 0 obj
<</Type /Font
/BaseFont /Helvetica-BoldOblique
/Subtype /Type1
/Encoding /WinAnsiEncoding
>>
endobj
2 0 obj
<</ProcSet [/PDF /Text /ImageB /ImageC /ImageI]
/Font <<
/F1 7 0 R
/F2 8 0 R
>>
>>
endobj
9 0 obj
<<
/Producer (Horde PDF)
/CreationDate (D:%d)
>>
endobj
10 0 obj
<<
/Type /Catalog
/Pages 1 0 R
/OpenAction [3 0 R /FitH null]
/PageLayout /OneColumn
>>
endobj
xref
0 11
0000000000 65535 f 
0000000484 00000 n 
0000000779 00000 n 
0000000009 00000 n 
0000000087 00000 n 
0000000257 00000 n 
0000000335 00000 n 
0000000577 00000 n 
0000000671 00000 n 
0000000877 00000 n 
0000000953 00000 n 
trailer
<<
/Size 11
/Root 10 0 R
/Info 9 0 R
>>
startxref
1057
%%EOF
%PDF-1.3
3 0 obj
<</Type /Page
/Parent 1 0 R
/Resources 2 0 R
/Contents 4 0 R>>
endobj
4 0 obj
<</Length 128>>
stream
2 J
0.57 w
BT /F1 8.00 Tf ET
BT 283.46 558.43 Td (First page) Tj ET
BT /F1 20.00 Tf ET
BT 283.46 274.96 Td (HELLO WORLD!) Tj ET

endstream
endobj
5 0 obj
<</Type /Page
/Parent 1 0 R
/Resources 2 0 R
/Contents 6 0 R>>
endobj
6 0 obj
<</Length 89>>
stream
2 J
0.57 w
BT /F1 20.00 Tf ET
BT /F2 12.00 Tf ET
BT 283.46 558.43 Td (Second page) Tj ET

endstream
endobj
1 0 obj
<</Type /Pages
/Kids [3 0 R 5 0 R ]
/Count 2
/MediaBox [0 0 595.28 841.89]
>>
endobj
7 0 obj
<</Type /Font
/BaseFont /Courier
/Subtype /Type1
/Encoding /WinAnsiEncoding
>>
endobj
8 0 obj
<</Type /Font
/BaseFont /Helvetica-BoldOblique
/Subtype /Type1
/Encoding /WinAnsiEncoding
>>
endobj
2 0 obj
<</ProcSet [/PDF /Text /ImageB /ImageC /ImageI]
/Font <<
/F1 7 0 R
/F2 8 0 R
>>
>>
endobj
9 0 obj
<<
/Producer (Horde PDF)
/CreationDate (D:%d)
>>
endobj
10 0 obj
<<
/Type /Catalog
/Pages 1 0 R
/OpenAction [3 0 R /FitH null]
/PageLayout /OneColumn
>>
endobj
xref
0 11
0000000000 65535 f 
0000000479 00000 n 
0000000774 00000 n 
0000000009 00000 n 
0000000087 00000 n 
0000000264 00000 n 
0000000342 00000 n 
0000000572 00000 n 
0000000666 00000 n 
0000000872 00000 n 
0000000948 00000 n 
trailer
<<
/Size 11
/Root 10 0 R
/Info 9 0 R
>>
startxref
1052
%%EOF
