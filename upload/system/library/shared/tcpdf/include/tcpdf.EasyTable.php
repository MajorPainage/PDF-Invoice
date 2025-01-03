<?php

// ============================================================+
// File name: class.TCPDF.EasyTable.php
// Begin: 2009-12-01
// Last Update : 2020-05-01 by [opencart-templates.co.uk]
// Author : Bretton Eveleigh
// Version : 0.4.3 BETA

// This class extends the functionality of TCPDF for easy
// formatting of data(text/images) in a tabular structure
// mimicing and improving on the EzTable method of the
// ROS PDF lib

class TCPDF_EasyTable extends TCPDF
{
    private $_CellAlignment;
    private $_CellWidths;
    private $_TableSettings;
    private $_TableData;
    private $_CellFillStyle = 0;
    private $_CellFontColor;
    private $_FillCell = 1;
    private $_FillImageCell = true;
    private $_HCellSpace = 0;
    private $_VCellSpace = 0;
    private $_HeaderCellWidths = null;
    private $_HeaderCellHeightRatio;
    private $_HeaderCellsFillColor;
    private $_HeaderCellsFontColor;
    private $_HeaderCellsFontSize;
    private $_HeaderCellsFontStyle; // added 25-01-2010... to change the style of the column header text
    private $_HeaderCellsFixedHeight;
    private $_CellMinimumHeight; // if set... if cell height is less than this it will be forced up to, table header cells are excluded
    private $_CellFixedHeight; // if set then the max cell height is not auto-calc, and is set manually...
    private $_AutoRepeatTableHeader = true; // by default the table header is repeated on each page...
    private $_HeaderFirstTablePerPageOnly = false; // only repeat the table header on the first table per page...
    private $_IsTableHeader = false;
    private $_TableRowFillColors; // added 26-01-2010... if defined is multi dim array of RBG colors, per row, indexed same as _TableData... RGB values only
    private $_FooterExclusionZone = 0; // the area the footer, where the table should not enter, added to the bottom margin
    private $_TableX;
    private $_TableY;
    private $_PageAdded = false;

    /**
     * Initiates the output of EasyTable to PDF, after all style/control params have been set
     *
     * @param array $tableData The multi-dim array representing table rows/cells for output
     * @param array $tableOptions The array of table options header/body
     * @return null
     * @author Bretton Eveleigh
     * @access public
     * @since 0.1 BETA (2009-12-01)
     */
    public function EasyTable($tableData, $tableOptions = null)
    {
        //$TCPDF_AutoPageBreak = $this->AutoPageBreak;
        //$this->SetAutoPageBreak(false,$PageDims['bm']);

        $this->_TableSettings = $tableOptions;
        $this->_TableData = $tableData;

        // rendering the table headers, will be done automatically on following pages:
        if (isset($this->_TableSettings['header'])) {
            $this->_ProcessTableHeader();
        }

        if ($this->_TableData) {
            foreach ($this->_TableData as $i => $tableRow) {
                $this->_ProcessTableRow($tableRow, $i);
            }
        }

        // $this->_PageAdded = false;
        //$this->SetAutoPageBreak($TCPDF_AutoPageBreak, $PageDims['bm']); // restore the auto paging setting
    }

    /**
     * @param $tableRow
     * @param $i
     * @throws Exception
     */
    private function _ProcessTableRow($tableRow, $i = -1) {
        if (empty($tableRow)) return false;

        $PageDims = $this->getPageDimensions(1); // fixes bug by always getting first page dimensions
        $pageMargins = $this->getMargins();
        $PageExtentY = $PageDims['hk'] - ($pageMargins['top'] + $pageMargins['bottom']);

        // Calculate all cell width
        $this->_CalculateCellWidths($tableRow);

        if ($this->_TableX)
            $this->x = $this->_TableX; // needs to be set per row...

        // define the fill style, per row
        switch ((int)$this->_CellFillStyle) {
            case 1: // fill all cells
                $this->_FillCell = 1;
                break;
            case 2: // fill alternate cells:
                if ((int)$this->_FillCell === 0)
                    $this->_FillCell = 1;
                else $this->_FillCell = 0;
                break;
        }

        // get the row height:
        $rowHeight = $this->_GetRowHeight($tableRow);

        // if row height is too big for page... raise error, notify user:
        if ($rowHeight > $PageExtentY) {
            throw new Exception("Row height(" . (int)$rowHeight . ") violation detected. The cell height is too large to fit on a single page(" . (int)$PageExtentY . "), single cells cannot span more than 1 page. Consider splitting the text into multiple cells, or try reducing the font size.", 100001);
        } elseif (($this->y + $rowHeight) > $PageExtentY) {
            // check if row fits, else new page
            // $this->_PageAdded = true;
            $this->AddPage();
            $this->SetY($pageMargins['top']);
            $this->_ProcessTableHeader(true);
        }

        $startX = $this->x;
        $this->_ProcessTableRowCells($tableRow, $rowHeight);
        $this->SetY(($this->y + $rowHeight));
        $this->x = $startX; // maintain x co-ord after each row write

        // add vertical row cell spacing, if defined:
        if ($this->_VCellSpace)
            $this->y += (float)$this->_VCellSpace;
    }


    /**
     * Writes table header to PDF, after processing related settings,
     * table headers [priv $_TableHeaders] are defined/supplied via EasyTable method
     *
     * @return null
     * @author Bretton Eveleigh
     * @access private
     * @since 0.1 (2009-12-01)
     */
    private function _ProcessTableHeader()
    {
        // if($this->_HeaderFirstTablePerPageOnly && !$PageAdded) return;

        $this->_IsTableHeader = true;

        if($this->_TableX) $this->x = $this->_TableX;
        if($this->_TableY) $this->y = $this->_TableY;

        // store current settings:
        $cellFillStyle = $this->_CellFillStyle;
        $cellFillColor = $this->FillColor;
        $cellFontColor = $this->TextColor;
        $cellFontStyle = $this->FontStyle;
        $cellFontSizePt = $this->FontSizePt;
        $cellHeightRatio = $this->cell_height_ratio;

        // apply header specific settings:
        if($this->_HeaderCellsFillColor){
            $this->SetFillColor($this->_HeaderCellsFillColor['R'], $this->_HeaderCellsFillColor['G'], $this->_HeaderCellsFillColor['B']);
        }

        if($this->_HeaderCellsFontColor){
            $this->SetTextColor($this->_HeaderCellsFontColor['R'], $this->_HeaderCellsFontColor['G'], $this->_HeaderCellsFontColor['B']);
        }

        if($this->_HeaderCellsFontStyle || $this->_HeaderCellsFontSize){
            $this->SetFont($this->FontFamily, $this->_HeaderCellsFontStyle, $this->_HeaderCellsFontSize);
        }

        if ($this->_HeaderCellHeightRatio) {
            $this->setCellHeightRatio($this->_HeaderCellHeightRatio);
        }

        $this->_CalculateCellWidths();

        $this->_FillCell = 1; // fill cells

        if (isset($this->_TableSettings['header'])) {
            $rowHeight = $this->_GetRowHeight($this->_TableSettings['header']);
            $this->_ProcessTableRowCells($this->_TableSettings['header'], $rowHeight);
            $this->_IsTableHeader = false;
        }

        // restore original settings
        $this->SetFont($this->FontFamily, $cellFontStyle, $cellFontSizePt);
        $this->SetCellHeightRatio($cellHeightRatio);
        $this->_CellFillStyle = $cellFillStyle;
        $this->FillColor = $cellFillColor;
        $this->TextColor = $cellFontColor;

        $this->Ln();

        if($this->_VCellSpace)
            $this->y += (float)$this->_VCellSpace;
    }

    /**
     * Writes table row cells to PDF, after processing related settings, table data(rows/cells) [priv $_TableData] are defined/supplied via EasyTable method
     *
     * @param array $tableRow an array of cell data - HTML/text or image
     * @param array $rowHeight
     * @param int $rowIndex the array index of the table row being processed
     * @author Bretton Eveleigh
     * @access private
     * @since 0.1 (2009-12-01)
     */
    private function _ProcessTableRowCells($tableRow, $rowHeight, $rowIndex = 0)
    {
        if(!$this->_IsTableHeader){
            if($this->_CellFontColor && is_array($this->_CellFontColor)){
                $RGB = $this->_CellFontColor;
                $this->SetTextColor($RGB['R'], $RGB['G'], $RGB['B']);
            } else {
                $this->SetTextColor(0, 0, 0); // RGB
            }
        }

        $curFillColor = $this->FillColor;
        $curFillStyle = $this->_FillCell;

        if(!$this->_IsTableHeader && $this->_TableRowFillColors && isset($this->_TableRowFillColors[$rowIndex])){ // check if a custom row bgcolor has been assigned
            $RGB = $this->_TableRowFillColors[$rowIndex];
            $this->SetFillColor($RGB[0], $RGB[1], $RGB[2]);
            $this->_FillCell = 1;
        }

        // generate the cells of PDF table:
        foreach ($tableRow as $i => $cellData){
            $cellIndex = $i;
            $cellWidth = $this->_GetCellWidth($cellIndex);

            if ($this->_IsTableHeader && isset($this->_TableSettings['header'])) {
                $cellOptions = $this->_TableSettings['header'][$i];
            } else {
                $cellOptions = $this->_TableSettings['body'][$i];
            }

            if(is_object($cellData) && !is_string($cellData)){
                $className = strtolower(get_class($cellData));

                switch ($className){
                    case 'pdfimage': // place the PDF image...
                        // $imageWidth = $cellData->GetImageWidth();
                        $this->_ProcessMultiCellImage($cellData, $cellWidth, $rowHeight, $cellIndex, $cellOptions);
                        break;
                    case 'simplexmlelement': // convert and process as HTML string...
                        $this->_ProcessMultiCellText($cellData, $cellWidth, $rowHeight, $cellIndex, $cellOptions);
                        break;
                }
            } else { // process as HTML string
                if (isset($cellData['label'])) {
                    $text = $cellData['label'];
                } else {
                    $text = $cellData;
                }
                $this->_ProcessMultiCellText($text, $cellWidth, $rowHeight, $cellIndex, $cellOptions);
            }
        }

        $this->FillColor = $curFillColor;
        $this->_FillCell = $curFillStyle;
        $this->_PageAdded = false;
    }

    /**
     * Performs all calculations and property assignments before calling TCPDF::MultiCell method to write to PDF
     * Text content is parsed as HTML to TCPDF::MultiCell, so all TCPDF valid HTML tags can be used in the text
     *
     * @param string $cellText text to display in cell... as HTML
     * @param float $cellWidth cell width
     * @param float $cellHeight cell height
     * @param int $cellIndex array index of the cell in table row
     * @param array $options
     * @return null
     * @author Bretton Eveleigh
     * @access private
     * @since 0.1 (2009-12-01)
     */
    private function _ProcessMultiCellText($cellText, $cellWidth, $cellHeight, $cellIndex, $options = array())
    {
        if (isset($options['align'])){
            $textAlign = $options['align'];
        } else {
            $textAlign = "L";
        }

        if (isset($options['border'])) {
            $border = $options['border'];
        } else {
            $border = 1;
        }

        $this->MultiCell($cellWidth, $cellHeight, $cellText, $border, $textAlign, $this->_FillCell, 0, '', '', true, 0, true);

        // Reset line style
        if ($options && isset($options['border'])) {
            $this->SetLineStyle(array('width' => 0.1, 'color' => array(0, 0, 0)));
        }

        if($this->_HCellSpace)
            $this->x += (float)$this->_HCellSpace;
    }

    /**
     * Performs all calculations and property assignments before calling TCPDF::MultiCell method to write image bounds to PDF, and thereafter process and write an image to PDF
     * 26 Jan 2010 - added support for different image formats, was restricted to JPEG previously
     *
     * @param object $pdfImage the pdfImage object to write to PDF
     * @param float $cellWidth cell width
     * @param float $cellHeight cell height
     * @param int $cellIndex cell index
     * @param array $options cell options
     * @return null
     * @author Bretton Eveleigh
     * @access private
     * @since 0.1 (2009-12-01) @revised 26 Jan 2010
     */
    private function _ProcessMultiCellImage(PDFImage $pdfImage, $cellWidth, $cellHeight, $cellIndex, $options = array())
    {
        $curFillStyle = $this->_FillCell;
        if(!$this->_FillImageCell) $this->_FillCell = 0; // no fill

        if (isset($options['align'])) {
            switch($options['align']) {
                case 'C':
                    $pdfImage->SetHorizontalAlignment('center');
                    $pdfImage->SetVerticalAlignment('middle');
                    break;
            }
        }

        if (isset($options['border'])) {
            $border = $options['border'];
        } else {
            $border = 1;
        }

        // first place the table cell...
        $cellStartX = $this->x; // store x
        $this->MultiCell($cellWidth, $cellHeight, "", $border, "C", $this->_FillCell, 0);
        $cellEndX = $this->x; // store cell x

        list($imgWidth, $imgHeight) = $pdfImage->GetCellImageDimensions($cellWidth, false, $this->getScaleFactor());

        // check that the image fits into the cell limits... else resize it using pdfimage scaling methods
        if ($imgWidth >= $cellWidth){
            $pdfImage->ScaleWidthTo($cellWidth - ($this->GetLineWidth() * 4));
        }

        if($imgHeight >= $cellHeight){
            $pdfImage->ScaleHeightTo($cellHeight - ($this->GetLineWidth() * 4));
        }

        // process any H/V alignment:
        $hAlignShim = 0;
        $vAlignShim = 0;

        $hAlign = $pdfImage->GetHorizontalAlignment(); // default is left
        $vAlign = $pdfImage->GetVerticalAlignment(); // default is top

        // custom
        if ($hAlign) {
            switch ($hAlign) {
                case 'right':
                    $hAlignShim = $cellWidth - ($imgWidth + ($this->LineWidth * 2));
                    break;
                case 'center':
                    $imgWidth = $imgWidth - 3;
                    $hAlignShim = ($cellWidth - $imgWidth) / 2;
                    break;
            }
        }

        if ($vAlign) {
            switch ($vAlign) {
                case 'bottom':
                    $vAlignShim = $cellHeight - ($imgHeight + ($this->GetLineWidth() * 2));
                    break;
                case 'middle':
                    $imgHeight = $imgHeight - 6;
                    $vAlignShim = ($cellHeight - $imgHeight) / 2;
                    break;
            }
        }

        // custom
        if ($pdfImage->Exists()){
            $this->x = $cellStartX; // move back to start x
            if($vAlignShim === 0)
                $vAlignShim = $this->GetLineWidth() * 2;
            if($hAlignShim === 0)
                $hAlignShim = $this->GetLineWidth();

            $imageX = (float)$this->x + $hAlignShim;
            $imageY = (float)$this->y + $vAlignShim;

            // Custom svg
            $imgSrc = $pdfImage->GetImageSrc();
            if ($imgSrc) {
                $this->ImageSVG('@' . $imgSrc, $imageX, $imageY, $imgWidth, $imgHeight, '', 'L', '', 0, false);
            } else {
                $this->Image($pdfImage->GetImagePath(), $imageX, $imageY, $imgWidth, $imgHeight, $pdfImage->GetImageFileType(), '', 'C', false, 300, '', false, false, 0);
            }
        }

        $this->_FillCell = $curFillStyle; // restore the cell fill style
        $this->x = $cellEndX;

        if($this->_HCellSpace) $this->x += (float)$this->_HCellSpace;
    }

    // SETTERS:

    /**
     * Set the minimum cell height, if cell height is less, it is resized to the height, if set it overrides any fixed height set previously
     *
     * @param float $height sets prop [priv $this->_CellMinimumHeight]
     * @author Bretton Eveleigh
     * @access public
     * @since 0.3 (2010-01-01)
     */
    public function SetCellMinimumHeight($height)
    {
        $this->_CellFixedHeight = null;
        $this->_CellMinimumHeight = (float)$height;
    }

    /**
     * Set cell height as fixed height, if set it overrides any minimum height set previously
     *
     * @param float $height sets prop [priv $this->_CellFixedHeight]
     * @author Bretton Eveleigh
     * @access public
     * @since 0.3 (2010-01-01)
     */
    public function SetCellFixedHeight($height)
    {
        $this->_CellMinimumHeight = null;
        $this->_CellFixedHeight = (float)$height;
    }

    /**
     * Set table header cell height as fixed height
     *
     * @param float $height sets prop [priv $this->_HeaderFixedHeight]
     * @author Bretton Eveleigh
     * @access public
     * @since 0.4.1 (2010-02-18)
     */
    public function SetHeaderCellFixedHeight($height)
    {
        $this->_HeaderCellsFixedHeight = (float)$height;
    }

    /**
     * Set whether the table header is repeated auto per page
     *
     * @param bool $repeat set prop [$this->_AutoRepeatTableHeader]
     * @author Bretton Eveleigh
     * @access public
     * @since 0.1 (2009-12-01)
     */
    public function SetTableHeaderPerPage($var)
    {
        $this->_AutoRepeatTableHeader = (bool) $var;
    }

    public function SetTableHeaderFirstTablePerPageOnly($var)
    {
        $this->_HeaderFirstTablePerPageOnly = (bool) $var;
    }

    /**
     * Sets the table header/cell text horizontal aligment via array, example: array('C','L',null,'R',''R): 'C' = center 'R' = right 'L' = left
     *
     * @param array $ArrayCellAlignment set prop [priv $this->_CellAlignment]
     * @return null
     * @author Bretton Eveleigh
     * @access public
     * @since 0.2 (2009-12-20)
     */
    public function SetCellAlignment($ArrayCellAlignment)
    {
        $this->_CellAlignment = $ArrayCellAlignment;
    }

    /**
     * Sets the table column widths via array example: array('20','30','auto',100)
     * Please note that the functionality of the 'auto' assignment is experimental and needs more work to make it totally accurate
     *
     * @param array $ArrayCellWidths set prop [priv $this->_CellWidths]
     * @author Bretton Eveleigh
     * @access public
     * @since 0.2 (2009-12-20)
     */
    public function SetCellWidths($ArrayCellWidths)
    {
        $this->_CellWidths = $ArrayCellWidths;
    }

    /**
     * Sets the cell fill per table row:
     * 0: no fill 1: fill all rows 2: fill alternate rows
     *
     * @param int $int set prop [priv $this->_CellFillStyle]
     * @author Bretton Eveleigh
     * @access public
     * @since 0.2 (2009-12-20)
     */
    public function SetCellFillStyle($int)
    {
        $this->_CellFillStyle = (int)$int;
    }

    /**
     * Set whether an 'image cell' will have the cell's fill style applied:
     *
     * @param bool $fill set prop [priv $this->_FillImageCell]
     * @return null
     * @author Bretton Eveleigh
     * @access public
     * @since 0.2 (2009-12-20)
     */
    public function SetFillImageCell($fill)
    {
        $this->_FillImageCell = (bool) $fill;
    }

    /**
     * Set the horizontal spacing between table cells
     *
     * @param float $var set prop [priv $this->_HCellSpace]
     * @author Bretton Eveleigh
     * @access public
     * @since 0.2 (2009-12-20)
     */
    public function SetHCellSpace($var)
    {
        $this->_HCellSpace = (float)$var;
    }

    /**
     * Set the vertical spacing between table cells
     *
     * @param float $var set prop [priv $this->_VCellSpace]
     * @author Bretton Eveleigh
     * @access public
     * @since 0.2 (2009-12-20)
     */
    public function SetVCellSpace($var)
    {
        $this->_VCellSpace = (float)$var;
    }

    /**
     * Set the table header cells background color, args are stored as array [priv $this->_HeaderCellsFillColor]
     *
     * @param int $R set the red RGB value
     * @param int $G set the green RGB value
     * @param int $B set the blue RGB value
     * @author Bretton Eveleigh
     * @access public
     * @since 0.2 (2009-12-20)
     */
    public function SetHeaderCellsFillColor($R, $G=-1, $B=-1)
    {
        $this->_HeaderCellsFillColor = array('R' => $R,'G' => $G,'B' => $B);
    }

    /**
     * Set custom table row fill colors, per row, by table row index, example:
     * $tablearray = array( array('Hello'), #--> row index 1 array('World) #--> row index 2 );
     * $colorarray = array( array(150,150,150), #--> color of row indexed as 1 array(255,255,255) #--> color of row indexed as 2 );
     *
     * @param array $colorsArray multi dim array of RGB values for each row
     * @author Bretton Eveleigh
     * @access private
     * @since 0.2 (2009-12-20)
     */
    public function SetTableRowFillColors(Array $colorsArray)
    {
        $this->_TableRowFillColors = $colorsArray;
    }

    /**
     * Set the table header cells font/text color, args are stored as array [priv $this->_HeaderCellsFontColor ]
     *
     * @param int $R set the red RGB value
     * @param int $G set the green RGB value
     * @param int $B set the blue RGB value
     * @author Bretton Eveleigh
     * @access public
     * @since 0.2 (2009-12-20)
     */
    public function SetHeaderCellsFontColor($R, $G=-1, $B=-1)
    {
        $this->_HeaderCellsFontColor = array('R' => $R,'G' => $G,'B' => $B
        );
    }

    /**
     * Set font size for header cells in pt
     * @param $int
     */
    public function SetHeaderCellsFontSize($int)
    {
        $this->_HeaderCellsFontSize = $int;
    }

    /**
     * Note that this method is depreciated, since TCPDF::MultiCell is set to process cell string data as HTML,
     *  so the text formatting can now be achieved by HTML tags like <B>text</B>, <I>text</I>... the HTML must be compatible with TCPDF's HTML requirements.
     *
     * @author Bretton Eveleigh
     * @access public
     * @since 0.2 (2009-12-20)
     */
    public function SetHeaderCellsFontStyle($var)
    {
        $this->_HeaderCellsFontStyle = $var;
    }

    /**
     * Header cell ratio(line-height)
     * @since 0.3 (2010-01-10)
     */
    public function SetHeaderCellHeightRatio($ratio)
    {
        $this->_HeaderCellHeightRatio = $ratio;
    }

    /**
     * Set the table cells font/text color, args are stored as array [priv $this->_CellFontColor]
     *
     * @param int $R set the red RGB value
     * @param int $G set the green RGB value
     * @param int $B set the blue RGB value
     * @author Bretton Eveleigh
     * @access public
     * @since 0.3 (2010-01-10)
     */
    public function SetCellFontColor($R, $G=-1, $B=-1)
    {
        $this->_CellFontColor = array('R' => $R,'G' => $G,'B' => $B);
    }

    /**
     * The height/area above the footer that the table must not enter, if needed, added the bottom margin...
     *
     * @param float $float
     * @author Bretton Eveleigh
     * @access public
     * @since 0.3 (2010-01-10)
     */
    public function SetFooterExclusionZone($float)
    {
        $this->_FooterExclusionZone = (float)$float;
    }

    /**
     * Set horizontal x coord for the left top corner of the table
     *
     * @param float $x the pdf x coord for table top left
     * @author Bretton Eveleigh
     * @access public
     * @since 0.3 (2010-01-10)
     */
    public function SetTableX($x)
    {
        $this->_TableX = (float)$x;
    }

    /**
     * Set horizontal y coord for the left top corner of the table
     *
     * @param float $y the pdf y coord for table top left
     * @author Bretton Eveleigh
     * @access public
     * @since 0.3 (2010-01-10)
     */
    public function SetTableY($y)
    {
        $this->_TableY = (float)$y;
    }

    /**
     * Calc/return the rows max cell height and cell width
     *
     * @param array $tableRow array of row cell data
     * @return the calculated max row height height
     * @author Bretton Eveleigh
     * @access public
     * @since 0.4 (2010-02-18)
     * @updated (2010-02-23) - bug fix
     */
    private function _GetRowHeight($tableRow)
    {
        $maxRowHeight = 0;
        if($this->_IsTableHeader && (float)$this->_HeaderCellsFixedHeight > 0){ // if header cell and fixed height is defined:
            $maxRowHeight = (float)$this->_HeaderCellsFixedHeight;
        } elseif(!$this->_IsTableHeader && (float)$this->_CellFixedHeight > 0){	// if data cell and data cell fixed height is defined:
            $maxRowHeight = (float)$this->_CellFixedHeight;
        } elseif ($tableRow) { // for all other cells we calc the height with routine below
            foreach ($tableRow as $cellIndex => $cellData){
                // SKip if empty
                if (empty($cellData)) continue;

                // check that the cell is not an object, if so process object...
                $cellWidth = $this->_GetCellWidth($cellIndex); // get the cell width

                if(is_object($cellData) && !is_string($cellData)){ // process the string... into table cell...
                    $className = strtolower(get_class($cellData));
                    switch ($className){
                        case 'pdfimage': // a PDF image object
                            // Custom
                            list($cellWidth, $cellHeight) = $cellData->GetCellImageDimensions($cellWidth, true);
                            break;
                        case 'simplexmlelement': // a SimpleXMLElement node
                            $cellData = trim($cellData);
                            $cellHeight = $this->GetCellHeightFixed($cellData, $cellWidth);
                            break;
                    }
                } else { // a text string, could be HTML...
                    // custom
                    if (isset($cellData['label'])) {
                        $text = $cellData['label'];
                    } else {
                        $text = trim($cellData);
                    }
                    $cellHeight = $this->GetCellHeightFixed($text, $cellWidth);
                }

                if ($cellHeight > $maxRowHeight) {
                    $maxRowHeight = (float)$cellHeight;
                }
            }
        }

        /**
         * if a minimum cell height is defined, check that the row height is not smaller, if it is then set it to the defined minumum cell height
         */
        if(!$this->_IsTableHeader && !is_null($this->_CellMinimumHeight) && (float)$this->_CellMinimumHeight > $maxRowHeight){
            $maxRowHeight = (float)$this->_CellMinimumHeight;
        }

        return ($maxRowHeight);
    }

    /**
     * Calc/return the cells height based on cell text length and cell width
     *
     * @param string $cellText the table cell text
     * @param float $cellWidth the cell width
     * @return the calculated cell height
     * @author Bretton Eveleigh
     * @access public
     * @since 0.4 (2010-01-10)
     * @revised 2010-02-17 - replaced methods - ::_GetLineHeight, ::_GetRowHeight and :: _GetMultiCellNumLines they have been deleted
     */
    public function GetCellHeightFixed($cellText, $cellWidth)
    {
        $this->startTransaction();
        $PageDims = $this->getPageDimensions(1);
        $pageMargins = $this->getMargins();
        $this->SetY(0);
        $this->setPage(1);
        $this->MultiCell($cellWidth, 1, (string) $cellText, 1, "L", 0, 2, 0, 0, true, 0, true);
        $cellBottomY = $this->GetY();
        $cellHeight = $cellBottomY;
        if ($this->PageNo() > 1) {
            $cellHeight += ($PageDims['hk'] - ($pageMargins['top'] + $pageMargins['bottom'])) * $this->PageNo();
        }
        $this->rollbackTransaction($this);
        return ($cellHeight);
    }

    /**
     * Calculate cell widths per row
     * @param $tableRow
     */
    private function _CalculateCellWidths() {
        $w1 = $this->GetInnerPageWidth() / 100;

        if ($this->_IsTableHeader) {
            $this->_HeaderCellWidths = array();
            if (!empty($this->_TableSettings['header'])) {
                foreach ($this->_TableSettings['header'] as $column) {
                    $this->_HeaderCellWidths[] = $column['width'] * $w1;
                }
            }
        } else {
            $this->_CellWidths = array();
            if (!empty($this->_TableSettings['body'])) {
                foreach ($this->_TableSettings['body'] as $column) {
                    $this->_CellWidths[] = $column['width'] * $w1;
                }
            }
        }
    }

    /**
     * Get the cells width as defined in cell/column width array [priv $this->_CellWidths]
     *
     * @param int $cellIndex the array index of the cell
     * @return cell width
     * @author Bretton Eveleigh
     * @access public
     * @since 0.1 (2009-12-01)
     */
    private function _GetCellWidth($cellIndex)
    {
        $pageMargins = $this->GetMargins();
        $cellWidth = 0;
        if ($this->_IsTableHeader) {
            if(isset($this->_HeaderCellWidths[$cellIndex])) { // custom column widths have been defined
                $cellWidth = (float)$this->_HeaderCellWidths[$cellIndex];
            }
        } else {
            if(isset($this->_CellWidths[$cellIndex])){ // custom column widths have been defined
                if ($this->_CellWidths[$cellIndex] === 'auto'){ // calculate width of auto columns
                    $autoWidthCells = array();
                    $allocatedWidth = 0;
                    foreach ($this->_CellWidths as $key => $cW){
                        if($cW === 'auto'){
                            $autoWidthCells[] = $key;
                        } elseif((float)$cW > 0){
                            $allocatedWidth += (float)$cW;
                        }
                    }
                    $unallocatedWidth = $this->getPageWidth() - $pageMargins['left'] - $pageMargins['right'] - $allocatedWidth;
                    if($this->_HCellSpace && isset($this->_TableSettings['header'])){
                        $unallocatedWidth -= ($this->_HCellSpace * (sizeof($this->_TableSettings['header']) - 1));
                    }
                    $cellAvailableWidth = $unallocatedWidth / sizeof($autoWidthCells);
                    foreach ($autoWidthCells as $key){
                        $this->_CellWidths[$key] = $cellAvailableWidth;
                    }
                    $cellWidth = $cellAvailableWidth;
                } else {
                    $cellWidth = (float)$this->_CellWidths[$cellIndex];
                }
            }
        }

        if (!$cellWidth) {
            if(isset($this->_TableSettings['header'])){
                $columnCnt = sizeof($this->_TableSettings['header']); // we will attempt to automatically calculate the cells widths, cells will be equally spaced across page width...
            } else {
                $columnCnt = sizeof($this->_TableData[0]);
            }
            $cellWidth = ($this->GetPageWidth() - ($pageMargins['left'] + $pageMargins['right'])) / $columnCnt;
        }

        return ($cellWidth);
    }

    /**
     * Default destructor.
     * - Overwritten to prevent rollbackTransaction deleting images
     * @public
     * @since 1.53.0.TC016
     */
    public function __destruct() {
        // cleanup
        //$this->_destroy(true);
    }

}
