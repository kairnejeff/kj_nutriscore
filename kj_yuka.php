<?php


class kj_yuka extends Module
{
    public $url;
    public function __construct() {
        $this->name = 'kj_yuka';
        $this->author = 'Jing LEI';
        $this->version = '1.1.2';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Yuka');
        $this->description = $this->l('Ajouter yuka score');
        $this->ps_versions_compliancy = array('min' => '1.7.1', 'max' => _PS_VERSION_);
        $this->url="https://world.openfoodfacts.org/api/v0/product/";
    }

    public function install() {
        if (!parent::install() || !$this->_installSql()
            //Pour les hooks suivants regarder le fichier src\PrestaShopBundle\Resources\views\Admin\Product\form.html.twig
            || ! $this->registerHook('displayAdminProductsCombinationBottom')
            || ! $this->registerHook('displayAdminProductsOptionsStepTop')
            || ! $this->registerHook('actionProductAttributeUpdate')
        ) {
            return false;
        }

        return true;
    }

    public function uninstall() {
        return parent::uninstall() && $this->_unInstallSql();
    }
    /**
     * Modifications sql du module
     * @return boolean
     */
    protected function _installSql() {
        $sqlInstall = "ALTER TABLE " . _DB_PREFIX_ . "product_attribute "
            . "ADD yuka INT NULL";
        $sqlInstall2 = "ALTER TABLE " . _DB_PREFIX_ . "product "
            . "ADD yuka INT NULL";
        $sqlInstall3 = "ALTER TABLE " . _DB_PREFIX_ . "product "
            . "ADD nutriscore Varchar(1) NULL";
        $sqlInstall4 = "ALTER TABLE " . _DB_PREFIX_ . "product_attribute "
            . "ADD nutriscore Varchar(1) NULL";
        $returnSql= Db::getInstance()->execute($sqlInstall)
            && Db::getInstance()->execute($sqlInstall2)
            && Db::getInstance()->execute($sqlInstall3)
            && Db::getInstance()->execute($sqlInstall4);

        return  $returnSql;
    }

    /**
     * Suppression des modification sql du module
     * @return boolean
     */
    protected function _unInstallSql() {
        $sqlUninstall = "ALTER TABLE " . _DB_PREFIX_ . "product_attribute "
            . "DROP yuka";
        $sqlUninstall2 = "ALTER TABLE " . _DB_PREFIX_ . "product "
            . "DROP yuka";
        $sqlUninstall3 = "ALTER TABLE " . _DB_PREFIX_ . "product_attribute "
            . "DROP nutriscore";
        $sqlUninstall4 = "ALTER TABLE " . _DB_PREFIX_ . "product "
            . "DROP nutriscore";
        $returnSql = Db::getInstance()->execute($sqlUninstall)
            &&Db::getInstance()->execute($sqlUninstall2)
            &&Db::getInstance()->execute($sqlUninstall3)
            &&Db::getInstance()->execute($sqlUninstall4);

        return  $returnSql;
    }
    public function _displayForm(){
        $defaultLang = (int)Configuration::get('PS_LANG_DEFAULT');
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs'
                ),
                'submit' => array(
                    'title' => $this->l('Mettre à jour nutriscore')
                )
            ),
        );
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->default_form_language =$defaultLang;
        $helper->module = $this;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitUpdate';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'uri' => $this->getPathUri()
        );

        return $helper->generateForm(array($fields_form));
    }

    public function getContent()
    {
        $html= '';
        if(Tools::isSubmit('submitUpdate')){
            $sql= 'SELECT id_product,ean13,reference
                FROM `' . _DB_PREFIX_ . 'product` where ean13 !=\' \'';
            $rq = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

            $sql= 'SELECT id_product,ean13,reference
                FROM `' . _DB_PREFIX_ . 'product_attribute` where ean13 !=\' \'';
            $rq_attributes = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

            foreach ($rq as $item) {
                if($item['ean13']!==""){
                    $nutriscore= $this->callAPI($item['ean13']);
                    $this->updateNutriscore($nutriscore,$item['id_product']);
                    $html.=$item['reference']." ";
                }
            }
            foreach ($rq_attributes as $item) {
                if($item['ean13']!==""){
                    $nutriscore= $this->callAPI($item['ean13']);
                    $this->updateAttributeNutriscore($nutriscore,$item['id_product']);
                    $html.=$item['reference']." ";
                }
            }
            $html.=$html.'Mettre à jour réussi';
        }
        return  $html.$this->_displayForm();
    }

    private function updateNutriscore($nutriscore,$idProduct){
        $sqlUpdate='update `' . _DB_PREFIX_ . 'product` set `nutriscore` =\''.strtolower($nutriscore). '\' where `id_product` ='.$idProduct;
        Db::getInstance()->execute($sqlUpdate);
    }

    private function updateAttributeNutriscore($nutriscore,$idProduct){
        $sqlUpdate='update `' . _DB_PREFIX_ . 'product_attribute` set `nutriscore` =\''.strtolower($nutriscore). '\' where `id_product` ='.$idProduct;
        Db::getInstance()->execute($sqlUpdate);
    }

    private function callAPI($ean)
    {
        $final=$this->url.$ean.".json";
        $result = json_decode(file_get_contents($final));
        if(isset($result->product->nutrition_grade_fr)){
            return $result->product->nutrition_grade_fr;
        }
        return ' ';
    }

    public function hookDisplayAdminProductsOptionsStepTop($params){
        $product=new Product($params['id_product']);
        if($product->hasAttributes()==0){
            return PrestaShop\PrestaShop\Adapter\SymfonyContainer::getInstance()->get('twig')->render('@Modules/kj_yuka/views/templates/hook/product.twig', [
                'yuka' => $product->yuka,
                'nutriscore'=> $product->nutriscore
            ]);
        }
        return;
    }

    public function hookDisplayAdminProductsCombinationBottom($params){
        $combinaison= new Combination($params['id_product_attribute']);
        $idProductAttribute = _PS_VERSION_ < '1.7' ? (int) Tools::getValue('id_product_attribute') : (int) $params['id_product_attribute'];
        $yuka = $combinaison->yuka;
        $nutriscore =$combinaison->nutriscore;
        return $this->get('twig')->render('@Modules/kj_yuka/views/templates/hook/yuka.twig', [
            'id_product_attribute' => $idProductAttribute,
            'yuka' => $yuka,
            'nutriscore' => $nutriscore,
        ]);
    }

    public function hookActionProductAttributeUpdate($params){
        $idProductAttribute=$params['id_product_attribute'];

        $combinations = Tools::getValue('combinations');
        if (!empty($combinations['combination_'.$idProductAttribute])) {
            $yuka = $combinations['combination_'.$idProductAttribute]['yuka'] ?? NULL;
            $nutriscore = $combinations['combination_'.$idProductAttribute]['nutriscore'] ?? NULL;
            $combination = new Combination($idProductAttribute);
            $combination->yuka=(int)$yuka;
            $combination->nutriscore=$nutriscore;
            $combination->save();
        }

    }
}