<?php
require_once __DIR__ . '/../lib.php';
require_role('user');

$uid = uid();
$form_id = intval($_GET['fid'] ?? 0);
$form = get_form_by_id($form_id);
if (!$form || $form['username'] !== $uid) {
    die('表单不存在或无权导出');
}

$fields_config = $form['fields_config'] ?? [];
$field_keys = array_keys($fields_config);
$subs = get_form_submissions($form_id, 1, 100000);

if (empty($subs)) {
    die('<script>alert("无数据可导出");history.back();</script>');
}

$field_display_names = ['身份证号' => '学籍号'];
$headers = array_map(function($k) use ($field_display_names) {
    return $field_display_names[$k] ?? $k;
}, $field_keys);

$rows = [];
foreach ($subs as $s) {
    $row = [];
    foreach ($field_keys as $k) {
        $row[] = $s['data'][$k] ?? '';
    }
    $rows[] = $row;
}

$content = build_xlsx($headers, $rows, $form['name']);
$filename = preg_replace('/[^a-zA-Z0-9_\x{4e00}-\x{9fa5}]/u', '_', $form['name']) . '_' . date('Ymd_His') . '.xlsx';

if ($content === false || strlen($content) < 100) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename*=UTF-8\'\''. rawurlencode($filename.".csv"));
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($rows as $row) fputcsv($out, $row);
    fclose($out);
    exit;
}

$user_dir = DATA_DIR . DS . uid();
if (!is_dir($user_dir)) @mkdir($user_dir, 0755, true);
@file_put_contents($user_dir . DS . $filename, $content);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename*=UTF-8\'\'' . rawurlencode($filename));
header('Content-Length: ' . strlen($content));
echo $content;
exit();

// ==============================================================
// 生成xlsx
// ==============================================================
function build_xlsx($headers, $rows, $sheetName) {
    $tmpDir = sys_get_temp_dir() . '/xlsx_' . uniqid();
    @mkdir($tmpDir, 0755, true);
    @mkdir($tmpDir . '/xl/worksheets/_rels', 0755, true);
    @mkdir($tmpDir . '/xl/_rels', 0755, true);
    @mkdir($tmpDir . '/xl/theme', 0755, true);
    @mkdir($tmpDir . '/xl/printerSettings', 0755, true);
    @mkdir($tmpDir . '/_rels', 0755, true);
    @mkdir($tmpDir . '/customXml/_rels', 0755, true);
    @mkdir($tmpDir . '/docProps', 0755, true);

    // [Content_Types].xml
    $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $ct .= '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' . "\n";
    $ct .= '<Default Extension="bin" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.printerSettings"/>' . "\n";
    $ct .= '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' . "\n";
    $ct .= '<Default Extension="xml" ContentType="application/xml"/>' . "\n";
    $ct .= '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' . "\n";
    $ct .= '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' . "\n";
    $ct .= '<Override PartName="/xl/theme/theme1.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>' . "\n";
    $ct .= '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' . "\n";
    $ct .= '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>' . "\n";
    $ct .= '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>' . "\n";
    $ct .= '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>' . "\n";
    $ct .= '<Override PartName="/docProps/custom.xml" ContentType="application/vnd.openxmlformats-officedocument.custom-properties+xml"/>' . "\n";
    $ct .= '<Override PartName="/customXml/item1.xml" ContentType="application/vnd.openxmlformats-officedocument.customXmlProperties+xml"/>' . "\n";
    $ct .= '</Types>';
    file_put_contents("$tmpDir/[Content_Types].xml", $ct);

    // _rels/.rels
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $rels .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . "\n";
    $rels .= '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' . "\n";
    $rels .= '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>' . "\n";
    $rels .= '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>' . "\n";
    $rels .= '<Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/custom-properties" Target="docProps/custom.xml"/>' . "\n";
    $rels .= '</Relationships>';
    file_put_contents("$tmpDir/_rels/.rels", $rels);

    // xl/_rels/workbook.xml.rels
    $wbrels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $wbrels .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . "\n";
    $wbrels .= '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' . "\n";
    $wbrels .= '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>' . "\n";
    $wbrels .= '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' . "\n";
    $wbrels .= '<Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="theme/theme1.xml"/>' . "\n";
    $wbrels .= '</Relationships>';
    file_put_contents("$tmpDir/xl/_rels/workbook.xml.rels", $wbrels);

    // xl/worksheets/_rels/sheet1.xml.rels
    $s1rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $s1rels .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . "\n";
    $s1rels .= '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/printerSettings" Target="../printerSettings/printerSettings1.bin"/>' . "\n";
    $s1rels .= '</Relationships>';
    file_put_contents("$tmpDir/xl/worksheets/_rels/sheet1.xml.rels", $s1rels);

    // xl/workbook.xml
    $sn = '信息导入';
    $wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $wb .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:dbsheet="http://web.wps.cn/et/2021/dbsheet">' . "\n";
    $wb .= '<fileVersion appName="xl" lastEdited="3" lowestEdited="5" rupBuild="9302"/>' . "\n";
    $wb .= '<workbookPr/>' . "\n";
    $wb .= '<bookViews><workbookView windowWidth="31740" windowHeight="11505"/></bookViews>' . "\n";
    $wb .= '<sheets><sheet name="' . $sn . '" sheetId="2" r:id="rId1"/></sheets>' . "\n";
    $wb .= '<definedNames>' . "\n";
    $wb .= '<definedName name="班级">#REF!</definedName>' . "\n";
    $wb .= '<definedName name="年级">#REF!</definedName>' . "\n";
    $wb .= '<definedName name="区">OFFSET(#REF!,MATCH(信息导入!$A1&amp;信息导入!$B1,#REF!&amp;#REF!,0),0,COUNTIFS(#REF!,信息导入!$A1,#REF!,信息导入!$B1))</definedName>' . "\n";
    $wb .= '<definedName name="省">#REF!</definedName>' . "\n";
    $wb .= '<definedName name="市">OFFSET(#REF!,MATCH(信息导入!$A1,#REF!,0),0,COUNTIF(#REF!,信息导入!$A1))</definedName>' . "\n";
    $wb .= '</definedNames>' . "\n";
    $wb .= '<calcPr calcId="191029"/>' . "\n";
    $wb .= '<extLst><ext uri="{B58B0392-4F1F-4190-BB64-5DF3571DCE5F}" xmlns:xcalcf="http://schemas.microsoft.com/office/spreadsheetml/2018/calcfeatures"><xcalcf:calcFeatures><xcalcf:feature name="microsoft.com:RD"/><xcalcf:feature name="microsoft.com:Single"/><xcalcf:feature name="microsoft.com:FV"/><xcalcf:feature name="microsoft.com:CNMTM"/><xcalcf:feature name="microsoft.com:LET_WF"/><xcalcf:feature name="microsoft.com:LAMBDA_WF"/><xcalcf:feature name="microsoft.com:ARRAYTEXT_WF"/></xcalcf:calcFeatures></ext></extLst>' . "\n";
    $wb .= '</workbook>';
    file_put_contents("$tmpDir/xl/workbook.xml", $wb);

    // xl/styles.xml
    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $styles .= '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006" mc:Ignorable="xr9" xmlns:xr9="http://schemas.microsoft.com/office/spreadsheetml/2016/revision9">' . "\n";
    $styles .= '<numFmts count="4">' . "\n";
    $styles .= '<numFmt numFmtId="41" formatCode="_ * #,##0_ ;_ * \-#,##0_ ;_ * &quot;-&quot;_ ;_ @_ "/>' . "\n";
    $styles .= '<numFmt numFmtId="42" formatCode="_ &quot;?&quot;* #,##0_ ;_ &quot;?&quot;* \-#,##0_ ;_ &quot;?&quot;* &quot;-&quot;_ ;_ @_ "/>' . "\n";
    $styles .= '<numFmt numFmtId="43" formatCode="_ * #,##0.00_ ;_ * \-#,##0.00_ ;_ * &quot;-&quot;??_ ;_ @_ "/>' . "\n";
    $styles .= '<numFmt numFmtId="44" formatCode="_ &quot;?&quot;* #,##0.00_ ;_ &quot;?&quot;* \-#,##0.00_ ;_ &quot;?&quot;* &quot;-&quot;??_ ;_ @_ "/>' . "\n";
    $styles .= '</numFmts>' . "\n";
    $styles .= '<fonts count="22">' . "\n";
    $fonts_arr = [
        '<font><sz val="11"/><color theme="1"/><name val="宋体"/><charset val="134"/><scheme val="minor"/></font>',
        '<font><sz val="10"/><color theme="1"/><name val="微软雅黑"/><charset val="134"/></font>',
        '<font><sz val="10"/><color theme="1"/><name val="宋体"/><charset val="134"/><scheme val="minor"/></font>',
        '<font><u/><sz val="11"/><color rgb="FF0000FF"/><name val="宋体"/><charset val="0"/><scheme val="minor"/></font>',
        '<font><u/><sz val="11"/><color rgb="FF800080"/><name val="宋体"/><charset val="0"/><scheme val="minor"/></font>',
        '<font><sz val="11"/><color rgb="FFFF0000"/><name val="宋体"/><charset val="0"/><scheme val="minor"/></font>',
        '<font><b/><sz val="18"/><color theme="3"/><name val="宋体"/><charset val="134"/><scheme val="minor"/></font>',
        '<font><i/><sz val="11"/><color rgb="FF7F7F7F"/><name val="宋体"/><charset val="0"/><scheme val="minor"/></font>',
        '<font><b/><sz val="15"/><color theme="3"/><name val="宋体"/><charset val="134"/><scheme val="minor"/></font>',
        '<font><b/><sz val="13"/><color theme="3"/><name val="宋体"/><charset val="134"/><scheme val="minor"/></font>',
        '<font><b/><sz val="11"/><color theme="3"/><name val="宋体"/><charset val="134"/><scheme val="minor"/></font>',
        '<font><sz val="11"/><color rgb="FF3F3F76"/><name val="宋体"/><charset val="134"/><scheme val="minor"/></font>',
        '<font><sz val="10"/><color theme="1"/><name val="宋体"/><charset val="134"/><scheme val="minor"/></font>',
        '<font><b/><sz val="11"/><color rgb="FF0070C0"/><name val="宋体"/><charset val="0"/><scheme val="minor"/></font>',
        '<font><sz val="11"/><color rgb="FF00B050"/><name val="宋体"/><charset val="0"/><scheme val="minor"/></font>',
        '<font><sz val="11"/><color rgb="FF7030A0"/><name val="宋体"/><charset val="0"/><scheme val="minor"/></font>',
        '<font><sz val="9"/><name val="宋体"/><charset val="134"/><scheme val="minor"/></font>',
        '<font><b/><sz val="11"/><color rgb="FF000000"/><name val="宋体"/><charset val="0"/><scheme val="minor"/></font>',
        '<font><sz val="11"/><color rgb="FF000000"/><name val="宋体"/><charset val="0"/><scheme val="minor"/></font>',
        '<font><i/><sz val="11"/><name val="宋体"/><charset val="0"/><scheme val="minor"/></font>',
        '<font><sz val="9"/><color theme="1"/><name val="宋体"/><charset val="134"/><scheme val="minor"/></font>',
        '<font><b/><sz val="9"/><color theme="1"/><name val="宋体"/><charset val="134"/><scheme val="minor"/></font>',
    ];
    foreach ($fonts_arr as $f) { $styles .= $f . "\n"; }
    $styles .= '</fonts>' . "\n";
    $styles .= '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor theme="6"/><bgColor indexed="64"/></patternFill></fill></fills>' . "\n";
    $styles .= '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color auto="1"/></left><right style="thin"><color auto="1"/></right><top style="thin"><color auto="1"/></top><bottom style="thin"><color auto="1"/></bottom><diagonal/></border></borders>' . "\n";
    $styles .= '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"><alignment vertical="center"/></xf></cellStyleXfs>' . "\n";
    $styles .= '<cellXfs count="9">' . "\n";
    $cellxfs_arr = [
        '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"><alignment vertical="center"/></xf>',
        '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment vertical="center"/></xf>',
        '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment vertical="center"/></xf>',
        '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" quotePrefix="1" applyFont="1"><alignment vertical="center"/></xf>',
        '<xf numFmtId="0" fontId="5" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment vertical="center"/></xf>',
        '<xf numFmtId="49" fontId="5" fillId="2" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1"><alignment vertical="center"/></xf>',
        '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1" applyProtection="1"><alignment vertical="center"/><protection locked="0"/></xf>',
        '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyProtection="1"><alignment vertical="center"/><protection locked="0"/></xf>',
        '<xf numFmtId="49" fontId="3" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1"><alignment vertical="center"/></xf>',
    ];
    foreach ($cellxfs_arr as $xf) { $styles .= $xf . "\n"; }
    $styles .= '</cellXfs>' . "\n";
    $styles .= '<cellStyles count="1"><cellStyle name="常规" xfId="0" builtinId="0"/></cellStyles>' . "\n";
    $styles .= '<dxfs count="0"/><tableStyles count="0" defaultTableStyle="TableStyleMedium2" defaultPivotStyle="PivotStyleLight16"/>' . "\n";
    $styles .= '</styleSheet>';
    file_put_contents("$tmpDir/xl/styles.xml", $styles);

    // xl/theme/theme1.xml
    $theme = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $theme .= '<theme xmlns="http://schemas.openxmlformats.org/drawingml/2006/main" name="Office Theme">' . "\n";
    $theme .= '<themeElements><clrScheme name="Office">' . "\n";
    $theme .= '<dk1><sysClr lastClr="000000"/></dk1><lt1><sysClr lastClr="FFFFFF"/></lt1>' . "\n";
    $theme .= '<dk2><srgbClr val="1F497D"/></dk2><lt2><srgbClr val="EEECE1"/></lt2>' . "\n";
    $theme .= '<accent1><srgbClr val="4F81BD"/></accent1><accent2><srgbClr val="C0504D"/></accent2>' . "\n";
    $theme .= '<accent3><srgbClr val="9BBB59"/></accent3><accent4><srgbClr val="8064A2"/></accent4>' . "\n";
    $theme .= '<accent5><srgbClr val="4BACC6"/></accent5><accent6><srgbClr val="F79646"/></accent6>' . "\n";
    $theme .= '<hlink><srgbClr val="0000FF"/></hlink><folHlink><srgbClr val="800080"/></folHlink>' . "\n";
    $theme .= '</clrScheme><fontScheme name="Office">' . "\n";
    $theme .= '<majorFont><latin typeface="宋体"/><ea typeface=""/><cs typeface=""/></majorFont>' . "\n";
    $theme .= '<minorFont><latin typeface="宋体"/><ea typeface=""/><cs typeface=""/></minorFont>' . "\n";
    $theme .= '</fontScheme><fmtScheme name="Office">' . "\n";
    $theme .= '<fillStyleLst><solidFill/><gradFill/><noFill/></fillStyleLst>' . "\n";
    $theme .= '<lnStyleLst><ln><solidFill><schemeClr val="accent1"/></solidFill></ln><ln><solidFill><schemeClr val="accent1"/></solidFill></ln><ln><solidFill><schemeClr val="accent1"/></solidFill></ln></lnStyleLst>' . "\n";
    $theme .= '<effectStyleLst><effectStyle><effectLst/></effectStyle><effectStyle><effectLst/></effectStyle><effectStyle><effectLst/></effectStyle></effectStyleLst>' . "\n";
    $theme .= '<bgFillStyleLst><solidFill/><gradFill/><noFill/></bgFillStyleLst>' . "\n";
    $theme .= '</fmtScheme></themeElements></theme>';
    file_put_contents("$tmpDir/xl/theme/theme1.xml", $theme);

    // docProps/core.xml
    $core = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $core .= '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">' . "\n";
    $core .= '<dc:creator>WPS Office</dc:creator>' . "\n";
    $core .= '<dcterms:created xsi:type="dcterms:W3CDTF">' . date('c') . '</dcterms:created>' . "\n";
    $core .= '<dcterms:modified xsi:type="dcterms:W3CDTF">' . date('c') . '</dcterms:modified>' . "\n";
    $core .= '</cp:coreProperties>';
    file_put_contents("$tmpDir/docProps/core.xml", $core);

    // docProps/app.xml
    $app = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $app .= '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">' . "\n";
    $app .= '<Application>Microsoft Office Excel</Application>' . "\n";
    $app .= '</Properties>';
    file_put_contents("$tmpDir/docProps/app.xml", $app);

    // docProps/custom.xml
    $custom = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $custom .= '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/custom-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">' . "\n";
    $custom .= '<property fmtid="{D5CDD505-2E9C-101B-9397-08002B2CF9AE}" pid="2" name="KSOProductBuildVer"><vt:lpwstr>2052-12.1.0.26895</vt:lpwstr></property>' . "\n";
    $custom .= '<property fmtid="{D5CDD505-2E9C-101B-9397-08002B2CF9AE}" pid="3" name="ICV"><vt:lpwstr>3D3104DE13954D2088C3AB089CA30F63_12</vt:lpwstr></property>' . "\n";
    $custom .= '<property fmtid="{D5CDD505-2E9C-101B-9397-08002B2CF9AE}" pid="4" name="CalculationRule"><vt:i4>0</vt:i4></property>' . "\n";
    $custom .= '</Properties>';
    file_put_contents("$tmpDir/docProps/custom.xml", $custom);

    // customXml/item1.xml
    $cxml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $cxml .= '<allowEditUser xmlns="https://web.wps.cn/et/2018/main" xmlns:s="http://schemas.openxmlformats.org/spreadsheetml/2006/main" hasInvisiblePropRange="0">' . "\n";
    $cxml .= '<rangeList sheetStid="2" master="" otherUserPermission="visible">' . "\n";
    $cxml .= '<arrUserId title="区域1" rangeCreator="" othersAccessPermission="edit"/>' . "\n";
    $cxml .= '</rangeList>' . "\n";
    $cxml .= '</allowEditUser>';
    file_put_contents("$tmpDir/customXml/item1.xml", $cxml);

    // customXml/_rels/item1.xml.rels
    $cxmlrels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $cxmlrels .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . "\n";
    $cxmlrels .= '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXmlProps" Target="itemProps1.xml"/>' . "\n";
    $cxmlrels .= '</Relationships>';
    file_put_contents("$tmpDir/customXml/_rels/item1.xml.rels", $cxmlrels);

    // customXml/itemProps1.xml
    $cxmlprops = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $cxmlprops .= '<ds:datastoreItem xmlns:ds="http://schemas.openxmlformats.org/officeDocument/2006/customXml" ds:itemID="{00000000-0000-0000-0000-000000000000}">' . "\n";
    $cxmlprops .= '<ds:propertySets><ds:propertySet></ds:propertySet></ds:propertySets>' . "\n";
    $cxmlprops .= '</ds:datastoreItem>';
    file_put_contents("$tmpDir/customXml/itemProps1.xml", $cxmlprops);

    // printerSettings (binary placeholder)
    file_put_contents("$tmpDir/xl/printerSettings/printerSettings1.bin", base64_decode('VU1JX01JTl9FTVBUWV9QQVJBTUVURVJfRk9SPQ=='));

    // sharedStrings
    $stringTable = [];
    $flatStrings = [];
    $sheetData = [$headers];
    foreach ($rows as $row) { $sheetData[] = $row; }
    foreach ($sheetData as $row) {
        foreach ($row as $cell) {
            $s = strval($cell);
            if (!isset($stringTable[$s])) {
                $stringTable[$s] = count($flatStrings);
                $flatStrings[] = $s;
            }
        }
    }

    $ssCount = count($flatStrings);
    $ssXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $ssXml .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . $ssCount . '" uniqueCount="' . $ssCount . '">';
    foreach ($flatStrings as $s) {
        $ssXml .= '<si><t>' . htmlspecialchars($s, ENT_XML1) . '</t></si>';
    }
    $ssXml .= '</sst>';
    file_put_contents("$tmpDir/xl/sharedStrings.xml", $ssXml);

    // xl/worksheets/sheet1.xml
    $colCount = count($headers);
    $colLetters = [];
    for ($c = 0; $c < $colCount; $c++) { $colLetters[] = chr(65 + $c); }
    $maxCol = $colLetters[$colCount - 1];
    $lastRow = count($sheetData);

    // 确定"学籍号"列的索引（用于应用文本格式样式 s="8"）
    $xuejihao_idx = array_search('学籍号', $headers);
    $text_style = ' s="8"'; // numFmtId=49 (@文本格式)

    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $sheetXml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:x14="http://schemas.microsoft.com/office/spreadsheetml/2009/9/main" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006" xmlns:etc="http://www.wps.cn/officeDocument/2017/etCustomData">' . "\n";
    $sheetXml .= '<sheetPr/><dimension ref="A1:' . $maxCol . $lastRow . '"/>' . "\n";
    $sheetXml .= '<sheetViews><sheetView tabSelected="1" zoomScale="115" zoomScaleNormal="115" workbookViewId="0"><selection activeCell="L2" sqref="L2"/></sheetView></sheetViews>' . "\n";
    $sheetXml .= '<sheetFormatPr defaultColWidth="9" defaultRowHeight="16.5" outlineLevelRow="2"/>' . "\n";
    $sheetXml .= '<cols>';

    $colWidths = ['15.27', '14.63', '16.37', '19.27', '18.63', '23.91', '18', '11.09', '13.45', '19.45', '15.73', '32.09'];
    for ($c = 1; $c <= $colCount; $c++) {
        $w = isset($colWidths[$c - 1]) ? $colWidths[$c - 1] : '13.45';
        $sheetXml .= '<col min="' . $c . '" max="' . $c . '" width="' . $w . '" style="1" customWidth="1"/>';
    }
    $sheetXml .= '<col min="' . ($colCount + 1) . '" max="16384" width="9" style="1"/></cols>' . "\n";
    $sheetXml .= '<sheetData>';

    foreach ($sheetData as $ri => $row) {
        $rIdx = $ri + 1;
        $sheetXml .= '<row r="' . $rIdx . '" spans="1:' . $colCount . '">';
        foreach ($row as $ci => $cell) {
            $cRef = $colLetters[$ci] . $rIdx;
            $strIdx = $stringTable[strval($cell)] ?? 0;
            // 学籍号列使用文本格式样式（s="8"），其余标题行使用 s="4"
            if ($ci === $xuejihao_idx) {
                $style = ' s="8"';
            } elseif ($rIdx == 1) {
                $style = ' s="4"';
            } else {
                $style = '';
            }
            $sheetXml .= '<c r="' . $cRef . '"' . $style . ' t="s"><v>' . $strIdx . '</v></c>';
        }
        $sheetXml .= '</row>';
    }
    $sheetXml .= '</sheetData>' . "\n";
    $sheetXml .= '<phoneticPr/>' . "\n";

    // 数据验证
    $sheetXml .= '<dataValidations count="11">' . "\n";
    $sheetXml .= '<dataValidation type="list" allowBlank="1" showInputMessage="1" showErrorMessage="1" sqref="A2:A19999"><formula1>省</formula1></dataValidation>' . "\n";
    $sheetXml .= '<dataValidation type="list" allowBlank="1" showInputMessage="1" showErrorMessage="1" sqref="B2:B19999"><formula1>市</formula1></dataValidation>' . "\n";
    $sheetXml .= '<dataValidation type="list" allowBlank="1" showInputMessage="1" showErrorMessage="1" sqref="C2:C19999"><formula1>区</formula1></dataValidation>' . "\n";
    $sheetXml .= '<dataValidation type="list" allowBlank="1" showInputMessage="1" showErrorMessage="1" errorTitle="警告：" error="请选择对应的年级" promptTitle="提示：" prompt="请点击单元格右侧三角按钮选择" sqref="E3:E1048576"><formula1>年级</formula1></dataValidation>' . "\n";
    $sheetXml .= '<dataValidation type="list" allowBlank="1" showInputMessage="1" showErrorMessage="1" errorTitle="警告：" error="请选择对应的班级。" promptTitle="提示：" prompt="请点击单元格右侧三角按钮选择" sqref="F2:F1048576"><formula1>班级</formula1></dataValidation>' . "\n";
    $sheetXml .= '<dataValidation type="list" allowBlank="1" showInputMessage="1" showErrorMessage="1" errorTitle="警告：" error="请选择对应的性别。" promptTitle="提示：" prompt="请点击单元格右侧三角按钮选择：男/女" sqref="H2:H1048576"><formula1>"男,女"</formula1></dataValidation>' . "\n";
    $sheetXml .= '<dataValidation type="list" allowBlank="1" showInputMessage="1" showErrorMessage="1" errorTitle="警告：" error="请选择对应的测量项目。" promptTitle="提示：" prompt="请点击单元格右侧三角按钮选择测量项目" sqref="I2:I1048576"><formula1>"侧弯,后凸,侧弯&amp;后凸"</formula1></dataValidation>' . "\n";
    $sheetXml .= '<dataValidation type="custom" allowBlank="1" showInputMessage="1" showErrorMessage="1" errorTitle="警告：" error="内容不符合:&quot;请输入11位手机号码&quot;" promptTitle="提示：" prompt="请输入11位手机号码" sqref="J2:J1048576"><formula1>AND(TRUE,ISNUMBER(--MID(J2,1,11)),LEN(J2)=11)</formula1></dataValidation>' . "\n";
    $sheetXml .= '<dataValidation type="date" operator="between" allowBlank="1" showInputMessage="1" showErrorMessage="1" errorTitle="日期错误" error="禁止输入小于1900/1/1日期或格式错误" promptTitle="提示：" prompt="输入格式为：年/月/日或年-月-日" sqref="K2:K1048576"><formula1>1</formula1><formula2>73051</formula2></dataValidation>' . "\n";
    $sheetXml .= '<dataValidation type="custom" allowBlank="1" showInputMessage="1" showErrorMessage="1" errorTitle="限制输入" error="禁止输入特殊字符" promptTitle="提示：" prompt="只支持纯字母、纯数字及字母数字混合录入" sqref="L2:L1048576"><formula1>AND(ISNUMBER(SUMPRODUCT(SEARCH(MID(L2,ROW(INDIRECT("1:"&amp;LEN(L2))),1),"0123456789abcdefghijklmnopqrstuvwxyz"))),NOT(OR(ISNUMBER(SUMPRODUCT(SEARCH("~*",L2))),ISNUMBER(SUMPRODUCT(SEARCH("~?",L2))),ISNUMBER(SUMPRODUCT(SEARCH("~~",L2))))))</formula1></dataValidation>' . "\n";
    $sheetXml .= '<dataValidation type="list" allowBlank="1" showInputMessage="1" showErrorMessage="1" sqref="E2"><formula1>#REF!</formula1></dataValidation>' . "\n";
    $sheetXml .= '</dataValidations>' . "\n";

    $sheetXml .= '<pageMargins left="0.69930555555555596" right="0.69930555555555596" top="0.75" bottom="0.75" header="0.3" footer="0.3"/><pageSetup paperSize="9" orientation="portrait"/></worksheet>';
    file_put_contents("$tmpDir/xl/worksheets/sheet1.xml", $sheetXml);

    // ZIP打包
    $zipFile = $tmpDir . '/output.xlsx';
    $zip = new ZipArchive();
    if (!$zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
        return false;
    }
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tmpDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($iter as $file) {
        if ($file->isDir()) continue;
        $localPath = substr($file->getPathname(), strlen($tmpDir) + 1);
        $localPath = str_replace('\\', '/', $localPath);
        $zip->addFile($file->getPathname(), $localPath);
    }
    $zip->close();

    $content = file_get_contents($zipFile);
    @unlink($zipFile);
    array_map('unlink', glob("$tmpDir/*", GLOB_MARK));
    @rmdir($tmpDir);
    return $content;
}