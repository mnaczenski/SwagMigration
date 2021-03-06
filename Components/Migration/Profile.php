<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

/**
 * Shopware SwagMigration Components - Profile
 *
 * Base profile which all other profiles extend from. Basically offers a generic interface to the source profiles
 * and checks if there is a method which returns a SQL query for the requested data. If so, in most cases the
 * query will be executed and the query object will be returned. In some cases the result will directly be returned.
 *
 * @category  Shopware
 * @package Shopware\Plugins\SwagMigration\Components\Migration
 * @copyright Copyright (c) 2012, shopware AG (http://www.shopware.de)
 */
abstract class Shopware_Components_Migration_Profile extends Enlight_Class
{
    /**
     * Global variable for the database object
     *
     * @var \Enlight_Components_Db.factory|?
     */
    protected $db;

    /**
     * Prefix of each database table in the profile
     *
     * @var
     */
    protected $db_prefix;

    /**
     * Database adapter type
     *
     * @var string
     */
    protected $db_adapter = 'PDO_MYSQL';

    /**
     * Array of the configuration
     *
     * @var
     */
    protected $config;

    /**
     * Default language of shopware
     *
     * @var
     */
    protected $default_language;

    /**
     * Default query limit for some specific operations
     * The system does not need to be able to import $default_limit entities per query
     * but it must be able to *select* that much entities within a reasonable time.
     *
     * @var
     */
    protected $default_limit = 1000;

    /**
     * Class constructor to open the database connection
     *
     * @param $config
     */
    public function __construct($config)
    {
        if (Shopware()->Plugins()->Backend()->SwagMigration()->Config()->debugMigration) {
            $this->db = new Shopware_Components_Migration_DbDecorator(Enlight_Components_Db::factory($this->db_adapter, $config));
        } else {
            $this->db = Enlight_Components_Db::factory($this->db_adapter, $config);
        }
        $this->db->getConnection();
        if (isset($config['prefix'])) {
            $this->db_prefix = $config['prefix'];
        }
    }

    /**
     * In some shops, any single variant of an product has assigned all the product's images
     * In order to sort this out, return 'true' in the shop's profile
     */
    public function checkForDuplicateImages()
    {
        return false;
    }

    /**
     * This function add the profile database prefix to the given table
     *
     * @param $table
     * @param null $alias
     * @return string
     */
    public function quoteTable($table, $alias = null)
    {
        if (!empty($this->db_prefix)) {
            $table = $this->db_prefix . $table;
        }

        return $this->db->quoteTableAs($table, $alias);
    }

    /**
     * This function returns the database information
     *
     * @return array
     */
    public function getDatabases()
    {
        $databases = $this->db->fetchCol('SHOW DATABASES');
        foreach ($databases as $key => $database) {
            if ($database == 'information_schema') {
                unset($databases[$key]);
            }
        }

        return $databases;
    }

    /**
     * Returns the database object
     *
     * @return Zend_Db_Adapter_Abstract
     */
    public function Db()
    {
        return $this->db;
    }

    /**
     * This function returns the configuration array
     *
     * @return mixed
     */
    public function Config()
    {
        if (!isset($this->config)) {
            $config = [];
            $sql = $this->getConfigSelect();
            $rows = $this->db->fetchAll($sql);
            foreach ($rows as $row) {
                if (!empty($row['type'])) {
                    switch ($row['type']) {
                        case 'bool':
                            if ($row['value'] == 'false') {
                                $row['value'] = false;
                            } else {
                                $row['value'] = (bool) $row['value'];
                            }
                            break;
                        case 'aarr':
                            $row['value'] = unserialize($row['value']);
                            break;
                        case 'str':
                        default:
                            break;
                    }
                }
                $config[$row['name']] = $row['value'];
            }
            $this->config = new ArrayObject($config, ArrayObject::ARRAY_AS_PROPS);
        }

        return $this->config;
    }

    /**
     * This function executes an sql statement with the given parameters
     *
     * @param $sql
     * @param int $count
     * @param int $offset
     * @return string
     */
    public function limit($sql, $count = 0, $offset = 0)
    {
        $count = intval($count);
        if ($count <= 0) {
            $count = 2147483647;
        }

        return $this->db->limit($sql, $count, $offset);
    }

    /**
     * This function returns the customer group select statement of the current profile
     *
     * @return mixed
     */
    public function getPriceGroupSelect()
    {
        return $this->getCustomerGroupSelect();
    }

    /**
     * This function returns the profile sub shops
     *
     * @return array
     */
    public function getShops()
    {
        if (!method_exists($this, 'getShopSelect')) {
            return;
        }

        return $this->db->fetchPairs($this->getShopSelect());
    }

    /**
     * This function returns the profile languages
     *
     * @return array
     */
    public function getLanguages()
    {
        if (!method_exists($this, 'getLanguageSelect')) {
            return;
        }

        return $this->db->fetchPairs($this->getLanguageSelect());
    }


    /**
     * This function returns the profile default language
     *
     * @return mixed
     */
    public function getDefaultLanguage()
    {
        if ($this->default_language === null && method_exists($this, 'getDefaultLanguageSelect')) {
            $this->default_language = $this->db->fetchOne($this->getDefaultLanguageSelect());
        }

        return $this->default_language;
    }

    /**
     * This function sets the profile default language
     *
     * @param $language
     */
    public function setDefaultLanguage($language)
    {
        $this->default_language = $language;
    }

    /**
     * Returns the customer groups, selected by the profile  sql
     *
     * @return array
     */
    public function getCustomerGroups()
    {
        if (!method_exists($this, 'getCustomerGroupSelect')) {
            return;
        }

        return $this->db->fetchPairs($this->getCustomerGroupSelect());
    }

    /**
     * Returns the price groups, selected by the profile sql
     *
     * @return array
     */
    public function getPriceGroups()
    {
        if (!method_exists($this, 'getPriceGroupSelect')) {
            return;
        }

        return $this->db->fetchPairs($this->getPriceGroupSelect());
    }

    /**
     * Returns the payment, selected by the profile  sql
     *
     * @return array
     */
    public function getProperties()
    {
        if (!method_exists($this, 'getPropertyOptionSelect')) {
            return;
        }

        return $this->db->fetchPairs($this->getPropertyOptionSelect());
    }

    /**
     * Returns the payment, selected by the profile  sql
     *
     * @return array
     */
    public function getPaymentMeans()
    {
        if (!method_exists($this, 'getPaymentMeanSelect')) {
            return;
        }

        return $this->db->fetchPairs($this->getPaymentMeanSelect());
    }

    /**
     * Returns the order states, selected by the profile sql
     *
     * @return array
     */
    public function getOrderStatus()
    {
        if (!method_exists($this, 'getOrderStatusSelect')) {
            return;
        }

        return $this->db->fetchPairs($this->getOrderStatusSelect());
    }

    /**
     * Query available configurator options for
     *
     * @return array
     */
    public function getConfiguratorOptions()
    {
        if (!method_exists($this, 'getConfiguratorOptionsSelect')) {
            return;
        }
        $result = $this->db->fetchCol($this->getConfiguratorOptionsSelect());

        $output = [];
        foreach ($result as $value) {
            $value = ucwords(strtolower($value));
            $output[$value] = $value;
        }

        return $output;
    }

    /**
     * Returns the article attributes, selected by the profile sql
     *
     * @return array
     */
    public function getAttributes()
    {
        if (!method_exists($this, 'getAttributeSelect')) {
            return;
        }

        return $this->db->fetchPairs($this->getAttributeSelect());
    }

    /**
     * Returns the tax rates, selected by the profile sql
     *
     * @return array
     */
    public function getTaxRates()
    {
        if (!method_exists($this, 'getTaxRateSelect')) {
            return;
        }

        return $this->db->fetchPairs($this->getTaxRateSelect());
    }

    /**
     * Returns the supplier, selected by the profile sql
     *
     * @return array
     */
    public function getSuppliers()
    {
        if (!method_exists($this, 'getSupplierSelect')) {
            return;
        }

        return $this->db->fetchPairs($this->getSupplierSelect());
    }

    /**
     * Returns the additional data for the article which will
     * be merged with the actual product
     *
     * @param $productId
     * @return mixed
     */
    public function getAdditionalProductInfo($productId)
    {
        if (!method_exists($this, 'getAdditionalProductSelect')) {
            return;
        }

        return $this->db->fetchRow($this->getAdditionalProductSelect($productId));
    }

    /**
     * Returns the categories, selected by the profile sql
     *
     * @param $offset
     * @return array
     */
    public function getCategories($offset)
    {
        $query = $this->queryCategories($offset);

        return $query->fetchAll();
    }

    /**
     * Query products, which have attributes associated.
     *
     * @param int $offset
     * @return mixed
     */
    public function queryAttributedProducts($offset = 0)
    {
        if (!method_exists($this, 'getAttributedProductsSelect')) {
            return;
        }
        $sql = $this->getAttributedProductsSelect();
        if (!empty($offset)) {
            $sql = $this->limit($sql, null, $offset);
        }

        return $this->db->query($sql);
    }

    /**
     * Get attributes for a given product id
     *
     * @param $id
     * @param int $offset
     * @return mixed
     */
    public function queryProductAttributes($id, $offset = 0)
    {
        if (!method_exists($this, 'getProductAttributesSelect')) {
            return;
        }
        $sql = $this->getProductAttributesSelect($id);
        if (!empty($offset)) {
            $sql = $this->limit($sql, null, $offset);
        }

        return $this->db->query($sql);
    }

    /**
     * Query products which have properties
     *
     * @param $offset
     * @return mixed
     */
    public function queryProductsWithProperties($offset)
    {
        if (!method_exists($this, 'getProductsWithPropertiesSelect')) {
            return;
        }
        $sql = $this->getProductsWithPropertiesSelect();
        if (!empty($offset)) {
            $sql = $this->limit($sql, null, $offset);
        }

        return $this->db->query($sql);
    }

    /**
     * Queries the properties for a given product id
     *
     * @param $id
     * @return mixed
     */
    public function queryProductProperties($id)
    {
        $sql = $this->getProductPropertiesSelect($id);
        if (!empty($offset)) {
            $sql = $this->limit($sql, null, $offset);
        }

        return $this->db->query($sql);
    }

    /**
     * Executes the profile category select statement with the given offset
     *
     * @param int $offset
     * @return Zend_Db_Statement_Interface
     */
    public function queryCategories($offset = 0)
    {
        $sql = $this->getCategorySelect();
        if (!empty($offset)) {
            $sql = $this->limit($sql, null, $offset);
        }

        return $this->db->query($sql);
    }

    /**
     * Executes the profile product category allocation select statement with the given offset
     *
     * @param int $offset
     * @return Zend_Db_Statement_Interface
     */
    public function queryProductCategories($offset = 0)
    {
        $sql = $this->getProductCategorySelect();
        if (!empty($offset)) {
            $sql = $this->limit($sql, null, $offset);
        }

        return $this->db->query($sql);
    }

    /**
     * Executes the profile product select statement with the given offset
     *
     * @param int $offset
     * @return Zend_Db_Statement_Interface
     */
    public function queryProducts($offset = 0)
    {
        $sql = $this->getProductSelect();
        if (!empty($offset)) {
            $sql = $this->limit($sql, null, $offset);
        }

        return $this->db->query($sql);
    }

    /**
     * Executes the profile product price select statement with the given offset
     *
     * @param int $offset
     * @return Zend_Db_Statement_Interface
     */
    public function queryProductPrices($offset = 0)
    {
        $sql = $this->getProductPriceSelect();
        if (!empty($offset)) {
            $sql = $this->limit($sql, null, $offset);
        }

        return $this->db->query($sql);
    }

    /**
     * Executes the profile customer select statement with the given offset
     *
     * @param int $offset
     * @return Zend_Db_Statement_Interface
     */
    public function queryCustomers($offset = 0)
    {
        $sql = $this->getCustomerSelect();
        if (!empty($offset)) {
            $sql = $this->limit($sql, null, $offset);
        }

        return $this->db->query($sql);
    }

    /**
     * Executes the profile product image select statement with the given offset
     *
     * @param int $offset
     * @return Zend_Db_Statement_Interface
     */
    public function queryProductImages($offset = 0)
    {
        $sql = $this->getProductImageSelect();
        if (!empty($offset)) {
            $sql = $this->limit($sql, null, $offset);
        }

        return $this->db->query($sql);
    }

    /**
     * Executes the profile product translation select statement with the given offset
     *
     * @param int $offset
     * @return Zend_Db_Statement_Interface
     */
    public function queryProductTranslations($offset = 0)
    {
        $sql = $this->getProductTranslationSelect();
        if (!empty($offset)) {
            $sql = $this->limit($sql, null, $offset);
        }

        return $this->db->query($sql);
    }

    /**
     * Executes the profile product rating select statement with the given offset
     *
     * @param int $offset
     * @return Zend_Db_Statement_Interface
     */
    public function queryProductRatings($offset = 0)
    {
        $sql = $this->getProductRatingSelect();
        if (!empty($offset)) {
            $sql = $this->limit($sql, null, $offset);
        }

        return $this->db->query($sql);
    }

    /**
     * Executes the profile order select statement with the given offset
     *
     * @param int $offset
     * @return Zend_Db_Statement_Interface
     */
    public function queryOrders($offset = 0)
    {
        $sql = $this->getOrderSelect();
        if (!empty($offset)) {
            $sql = $this->limit($sql, null, $offset);
        }

        return $this->db->query($sql);
    }

    /**
     * Executes the profile order detail select statement with the given offset
     *
     * @param int $offset
     * @return Zend_Db_Statement_Interface
     */
    public function queryOrderDetails($offset = 0)
    {
        $sql = $this->getOrderDetailSelect();
        if (!empty($offset)) {
            $sql = $this->limit($sql, null, $offset);
        }

        return $this->db->query($sql);
    }

    /**
     * Executes the profile ESD order select statement with the given offset
     *
     * @param int $offset
     * @return Zend_Db_Statement_Interface
     */
    public function queryEsdOrder($offset = 0)
    {
        $sql = $this->getEsdOrderSelect();
        if (!empty($offset)) {
            $sql = $this->limit($sql, null, $offset);
        }

        return $this->db->query($sql);
    }


    /**
     * Returns a rough estimation of number of entities to import
     * No need for correctness, only the estimated time depends on this
     *
     * @param $for
     * @return bool|string
     */
    public function getEstimation($for)
    {
        if (!method_exists($this, 'getEstimationSelect')) {
            return false;
        }
        $sql = $this->getEstimationSelect($for);

        return $this->db->fetchOne($sql);
    }

    /**
     *
     * @return bool|string
     */
    public function queryArticleDownload()
    {
        if (!method_exists($this, 'getDownloadSelect')) {
            return false;
        }
        $sql = $this->getDownloadSelect();

        return $this->db->query($sql);
    }

    /**
     *
     * @return bool|string
     */
    public function queryArticleDownloadESD()
    {
        if (!method_exists($this, 'getDownloadEsdSelect')) {
            return false;
        }
        $sql = $this->getDownloadEsdSelect();

        return $this->db->query($sql);
    }
}
