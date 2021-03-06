<?php
/**
 * This class load the xml and gets the data for each product in the xml file.
 *
 * @category    C4B
 * @package     C4B_XmlImport
 * @license     http://opensource.org/licenses/osl-3.0.php Open Software Licence 3.0 (OSL-3.0)
 * @author      Dominik Meglic <meglic@code4business.de>
 * @copyright   code4business Software GmbH
 */
class C4B_XmlImport_Model_Products
{    
    const VALIDATION_RESULT_FILE_ERROR = 0;
    const VALIDATION_RESULT_OK = 1;
    const VALIDATION_RESULT_NO_ROOT_NODE = 2;
    const VALIDATION_RESULT_NO_PRODUCT_NODES = 3;
    
    const XML_NODE_NAME_ROOT = 'products';
    const XML_NODE_NAME_PRODUCT = 'product';
    
    /**
     * Validate XML file for syntax errors and basic node presence.
     * @param string $filePath
     * @return string
     */
    public function validateFile($filePath)
    {
        /* @var $messageHandler C4B_ProductImport_Model_MessageHandler */
        $messageHandler = Mage::getSingleton('xmlimport/messageHandler');
        $result = self::VALIDATION_RESULT_OK;
        $nodeCount = 0;
        $xmlParser = xml_parser_create();
        $xmlReader = new XMLReader();

        try
        {
            if( !($xmlFile = fopen($filePath, 'r')) )
            {
                return self::VALIDATION_RESULT_FILE_ERROR;
            }

            while( $result == self::VALIDATION_RESULT_OK && ($readData = fread($xmlFile, 4096)) )
            {
                if( !xml_parse($xmlParser, $readData, feof($xmlFile)) )
                {
                    $messageHandler->addErrorsForFile(
                        basename($filePath), ' ',
                        sprintf('XML Syntax error: %s at line %d, column %d.',
                            xml_error_string(xml_get_error_code($xmlParser)),
                            xml_get_current_line_number($xmlParser),
                            xml_get_current_column_number($xmlParser)
                        )
                    );
                    $result = self::VALIDATION_RESULT_FILE_ERROR;
                }
            }

            if( $result == self::VALIDATION_RESULT_OK)
            {
                $xmlReader->open($filePath);
                if (!$xmlReader->next('products'))
                {
                    $result = self::VALIDATION_RESULT_NO_ROOT_NODE;
                } else
                {
                    $nodeCount = $this->_countProductNodes($xmlReader);
                }
            }

        } catch(Exception $e)
        {
            $messageHandler->addErrorsForFile(basename($filePath), ' ', $e->getMessage());
        }

        $xmlReader->close();
        fclose($xmlFile);
        xml_parser_free($xmlParser);

        if( $nodeCount == 0 && $result == self::VALIDATION_RESULT_OK)
        {
            $result = self::VALIDATION_RESULT_NO_PRODUCT_NODES;
        }
        else if( $result == self::VALIDATION_RESULT_OK)
        {
            $messageHandler->addNotice("File contains {$nodeCount} product nodes");
        }

        return $result;
    }
    
    /**
     * Processes given xml file by iterating over product nodes and extracting data into array
     * @param string $filePath
     * @return boolean
     */
    public function processFile($filePath)
    {
        $messageHandler = Mage::getSingleton('xmlimport/messageHandler');
        /* @var $productBuilder C4B_XmlImport_Model_Products_ProductBuilder */
        $productBuilder = Mage::getModel('xmlimport/products_productBuilder');
        $productNodePosition = 0;
        $xmlReader = new XMLReader();
        $xmlReader->open($filePath);
        $products = array();
        while($xmlReader->read())
        {
            if($xmlReader->nodeType != XMLReader::ELEMENT || $xmlReader->name != self::XML_NODE_NAME_PRODUCT)
            {
                continue;
            }
            $productNodePosition++;
            $productData = $productBuilder->getProductData( $xmlReader->expand() );
            if( count($productBuilder->getErrors()) > 0 )
            {
                $messageHandler->addError("Product at position {$productNodePosition} has errors:");
            }
            if($productData == null)
            {
                $messageHandler->addError('Product will not be imported');
            } else
            {
                foreach ($productData as $productDataRow)
                {
                    $products[] = $productDataRow;
                }
            }
            $messageHandler->addErrorsForFile( basename($filePath), $productNodePosition, $productBuilder->getErrors() );
        }
        
        return $products;
    }


    /**
     * Count the number of <product> nodes inside the given XML stream.
     * @param XMLReader $xmlReader
     * @return int
     */
    protected function _countProductNodes($xmlReader)
    {
        $nodeCount = 0;
        while( $xmlReader->read() )
        {
            if($xmlReader->nodeType == XMLReader::ELEMENT && $xmlReader->name == 'product')
            {
                $nodeCount++;
            }
        }
        return $nodeCount;
    }
}
