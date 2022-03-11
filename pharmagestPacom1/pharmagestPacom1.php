<?php

use Spatie\ArrayToXml\ArrayToXml;

if (!defined('_PS_VERSION_')) {
    exit;
}

class pharmagestPacom1 extends Module
{
    public function __construct()
    {
        $this->name = 'pharmagestPacom1';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Agence Pacom1';
        $this->ps_versions_compliancy = [
            'min' => '1.6',
            'max' => _PS_VERSION_
        ];
        $this->bootstrap = true;
        $this->cron_url = _PS_BASE_URL_ . _MODULE_DIR_ . 'pharmagestPacom1/cron.php?token=' . substr(Tools::encrypt('pharmagestPacom1/cron'), 0, 10);

        parent::__construct();

        $this->displayName = $this->l('Gestion erp pharmagest');
        $this->description = $this->l('Gestion erp de la société pharmagest');

        $this->dependencies = array('multitrackingbo');
        $this->dependencies = array('wkcombinationcustomize');
        $this->confirmUninstall = $this->l('etes vous sur de vouloir supprimer le module ??');

        if (!Configuration::get('PHARMAGEST_PACOM1')) {
            $this->warning = $this->l('Aucun nom trouver');
        }
    }

    public function install()
    {
        return (parent::install()
            && $this->registerHook('leftColumn')
            && $this->registerHook('header')
            && $this->registerHook('actionPaymentConfirmation')
            && Configuration::updateValue('PHARMAGEST_PACOM1', 'my friend'));
    }

    public function uninstall()
    {
        return (parent::uninstall()
            && Configuration::deleteByName('PHARMAGEST_PACOM1'));
    }

    //fonction affichant le formulaire
    public function displayForm()
    {
        // formulaire du champ de configuration
        $form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('lien cron : ' . $this->cron_url . ' '),
                ],
            ],
        ];
        $helper = new HelperForm();
        return $helper->generateForm([$form]);
    }

    // fonction recupérant la valeur de configuration du module
    public function getContent()
    {
        return $this->displayForm();
    }

    public function offiConnectRequest($host, $path, $login, $pass, $data)
    {

        //compression au format gzip du xml envoy� en param�tre
        //puis encodage du r�sultat en base64
        $data64z = base64_encode(gzencode($data));

        //initialisation de curl
        $curl = curl_init();

        //d�claration / initialisations des options de curl
        curl_setopt_array($curl, array(
            //retourne le r�sulat � curl sans l'afficher
            CURLOPT_RETURNTRANSFER => 1,
            //initialisation de l'url appel�
            CURLOPT_URL => $host . $path,
            //affectation du USER AGENT
            CURLOPT_USERAGENT => 'LGPI',
            //permet d'indiquer � PHP de faire un HTTP POST
            CURLOPT_POST => 1,
            //d�finition des variables � passer lors du POST
            CURLOPT_POSTFIELDS => array(
                'login' => $login,
                'password' => $pass,
                'data' => $data64z
            )
        ));

        //execution de la requete
        $resp = curl_exec($curl);

        //on ferme la connexion
        curl_close($curl);

        //on retourne le r�sultat de la requ�te
        return $resp;
    }

    public static function getIdByReferenceAttribute($reference)
    {
        if (empty($reference)) {
            return 0;
        }

        if (!Validate::isReference($reference)) {
            return 0;
        }

        $query = new DbQuery();
        $query->select('p.id_product');
        $query->from('product_attribute', 'p');
        $query->where('p.reference = \'' . pSQL($reference) . '\'');

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query);
    }

    public function pharmagestStock()
    {   
        Module::getInstanceByName('WkCombinationcustomize');
        // paramettre officonnect //
        $type = "STOCK"; // STOCK / VENTE / FACINT / PROMO
        $url = 'https://officonnect.pharmagest.com/';
        $page = 'impexboutique.php';
        $login = 'testss2i';
        $pass = 'D0n46j0A';

        if ($type == "STOCK") {
            //on d�clare le xml permettant de faire l'interrogation de stock
            $xml_demande = '<?xml version="1.0" encoding="UTF-8"?>
                                    <beldemande date="' . date('Y-m-d') . '" version="1.6" json="true" format="REQUEST">
                                    <request type="SSTOCK" num_pharma="' . $login . '" stock_differentiel="false"></request>
                                    </beldemande>';
            var_dump($xml_demande);

            //on execute la requ�te au serveur OffiConnect
            $fp = fopen(__DIR__ . '/tmp/stock - ' . $login . '.json', 'w');
            $retour_curl = $this->offiConnectRequest($url, $page, $login, $pass, $xml_demande);
            var_dump($retour_curl);
            //on �crit un fichier xml pour le retour
            fwrite($fp, $retour_curl);
            fclose($fp);
        }

        $json_file_name = 'stock - ' . $login . '.json';
        $json_file = __DIR__ . '/tmp/' . $json_file_name;
        $stock = json_decode(file_get_contents($json_file), true);
        foreach ($stock as $info_stock => $donnees_stock) {
            foreach ($donnees_stock['sstock']['produit'] as $info_produit => $donnees_produit) {
                foreach ($donnees_produit['zone'] as $info_zone => $donnees_zone) {
                    $code_produit[] = $donnees_produit['codeproduit'];
                    $mini_produit[] = $donnees_zone['@mini'];
                    $stock_produit[] =  $donnees_zone['@stock'];
                }
            }
        }
        foreach ($code_produit as $key => $value) {
            var_dump($value);
            // je suis dans un produit mere
            if ($id = Product::getIdByReference($value)) :
                echo "le produit est un produit mere\n";
                $pharmagest_product = new Product($id);

                // je verfie la quantité du produit
                if ((intval($stock_produit[$key]) < $mini_produit[$key] || intval($stock_produit[$key]) == 0 ) && intval($id) > 0) :
                    echo "quantitté minimum insufisante\n";
                    $pharmagest_product->active = 0;
                    $pharmagest_product->update();
                else :
                    echo "quantitté minimum suffisante\n";
                    $pharmagest_product->active = 1;
                    $pharmagest_product->update();
                endif;

            // je suis dans un produit fille
            elseif ($id = $this->getIdByReferenceAttribute($value)) :
                var_dump($id);
                echo "le produit est un produit fille\n";

                // je recupere les info du produit en fontion de l'id
                $product = new Product($id);
                echo "<pre>";
                print_r($product);
                echo "</pre>";
                $attribute = $product->getAttributeCombinations();
                $key_tab_atribut = array_search($value, array_column($attribute,"reference"));
                $id_attribut = $attribute[$key_tab_atribut]['id_product_attribute'];

                echo($value."-> ".$key_tab_atribut);
                echo "<pre>";
                print_r($attribute);
                echo "</pre>";                
                var_dump($id_attribut);
                echo(intval($stock_produit[$key]));
                echo"<br>";
                echo($mini_produit[$key]);
                if ((intval($stock_produit[$key]) < $mini_produit[$key] || intval($stock_produit[$key]) == 0 ) && intval($id_attribut) > 0) :
                    echo "quantitté minimum insufisante\n";

                    // on recupere les info de la declinaison dans la database
                    $combiData = WkCombinationStatus::getCombinationStatus(
                        $product->id,
                        $id_attribut,
                        $product->id_shop_default
                    );
                    var_dump($combiData);
                    // on verifie si la declinaison se trouve dans la base de données
                    if (!$combiData) :
                        $objCombiStatus = new WkCombinationStatus();
                        $objCombiStatus->id_ps_product = (int) $product->id;
                        $objCombiStatus->id_ps_product_attribute = (int) $id_attribut;
                        $objCombiStatus->id_shop = $product->id_shop_default;
                        $objCombiStatus->save();
                    endif;
                else :
                    echo "quantitté minimum suffisante\n";
                    $enable = WkCombinationStatus::deleteORActiveSinglePsCombination($id_attribut);
                endif;
            endif;
            
        }
    }
    public function hookactionPaymentConfirmation(array $params)
    {
        /* recuperation des information du client er produits */
        $id_order = $params['id_order'];
        $order = new Order((int) $id_order);
        $customer = new Customer($order->id_customer);
        $products = $order->getProducts();
        $adresses = new CustomerAddress($order->id_address_delivery);
        $gender = new Gender($customer->id_gender);
        $orderCarrier = new OrderCarrier($order->getIdOrderCarrier());


        // paramettre officonnect //
        $type = "VENTE"; // STOCK / VENTE / FACINT / PROMO
        $url = 'https://officonnect.pharmagest.com/';
        $page = 'impexboutique.php';
        $login = 'testss2i';
        $pass = 'D0n46j0A';

        /* information client */
        $customer_lastname = $customer->lastname;
        $customer_firstname = $customer->firstname;
        $customer_id = $order->id_customer;
        $customer_adress1 = $adresses->address1;
        $customer_adress2 = $adresses->address2;
        $customer_postcode = $adresses->postcode;
        $customer_city = $adresses->city;
        $customer_email = $customer->email;
        $customer_phone1 = $adresses->phone;
        $customer_phone2 = $adresses->phone_mobile;
        $customer_birth = $customer->birthday;
        $customer_country = $adresses->country;
        $customer_gender = $gender->name[1];

        if ($customer_gender == 'M') :
            $customer_gender = 'H';
        elseif ($customer_gender == 'Mme') :
            $customer_gender = 'F';
        endif;


        /* information facture */
        $bills_id = str_pad($id_order, 7, '0', STR_PAD_LEFT);
        $bills_total_HT = round($order->total_paid_tax_excl, 2);
        $bills_total_TVA = round($order->total_paid_tax_incl, 2);
        $bills_total_TTC = round($order->total_paid, 2);
        $sale_date = $order->date_add;



        /* declaration tableau de produits pour pharmagest */
        $pharmagest_array_product = array();

        /* boucle permettant de recuperer les produits de la commande */
        foreach ($products as $product) {

            /* information produit */
            $product_id = $product['id_product'];
            $product_name = $product['product_name'];
            $product_reference = $product['product_reference'];
            $product_quantity =  $product['product_quantity'];
            $product_unit_HT = round($product['unit_price_tax_excl'], 2);
            $product_unit_TTC = round($product['unit_price_tax_incl'], 2);
            $tax = $product["tax_rate"];

            /* recuperation de l'isbn parent du produits */
            $product_isbn = $product['isbn'];

            /* la valeur 1 correspond a l'organisme elvetis */
            if ($product_isbn == 2) {

                $pharmagest_array_product['lignevente'][] = [

                    'codeproduit' => $product_reference,
                    'designation_produit' => ['_cdata' => $product_name],
                    'quantite' => $product_quantity,
                    'prix_brut' => $product_unit_HT,
                    'remise' => 0,
                    'prix_net' => $product_unit_TTC,
                    'tauxtva' => $tax

                ];

                $pharmagest_array_product['lignevente'][max(array_keys($pharmagest_array_product['lignevente']))] = array_merge(
                    $pharmagest_array_product['lignevente'][max(array_keys($pharmagest_array_product['lignevente']))],
                    array('_attributes' => [
                        'numero_lignevente' => intval(max(array_keys($pharmagest_array_product['lignevente'])) + 1),
                    ])
                );
            }
        }

        if (!empty($pharmagest_array_product)) {

            /* ont verifie si une exoneration de tva est presente 
               si il n'ya aucune tax alors une exoneration est effectuer (1) */
            $exoneration_tva = 0;
            if ($tax == 0) : $exoneration_tva = 1;
            endif;

            $sale_date = date('Y-m-d\TH:i:s');

            /* tableau contenant les infos de livraison de pharmagest */
            $pharmagest_array_final = [
                'infact' => [
                    'vente' => [
                        '_attributes' => [
                            'num_pharma' => $login,
                            'numero_vente' => $bills_id,
                        ],
                        'client' => [
                            '_attributes' => [
                                'client_id' => $customer_id,
                            ],
                            'nom' => $customer_lastname,
                            'prenom' => $customer_firstname,
                            'datenaissance' => $customer_birth,
                            'adresse_facturation' => [
                                'rue1' => [
                                    '_cdata' => $customer_adress1,
                                ],
                                'rue2' => [
                                    '_cdata' => $customer_adress2,
                                ],
                                'codepostal' => [
                                    '_cdata' => $customer_postcode,
                                ],
                                'ville' => [
                                    '_cdata' => $customer_city,
                                ],
                                'pays' => [
                                    '_cdata' => $customer_country,
                                ],
                                'tel' => [
                                    '_cdata' => $customer_phone1,
                                ],
                                'email' => ['_cdata' =>
                                $customer_email,],
                            ],
                            'sexe' => $customer_gender,
                        ],
                        'date_vente' => $sale_date,
                        'montant_port_ht' => $bills_total_HT = $bills_total_TTC - $bills_total_HT,
                        'tauxtva_port' => $tax,
                        'total_ttc' => $bills_total_TTC,
                        'exoneration_tva' => $exoneration_tva,
                    ]
                ]
            ];


            /* on integre notre tableau de produit dans notre tableau livraison */
            $pharmagest_array_final['infact']['vente'] += $pharmagest_array_product;

            /* root de notre fichier xml */
            $xml_root = [
                'rootElementName' => 'beldemande',
                '_attributes' => [
                    'version' => '1.6',
                    'date' => substr($sale_date, 0, -9),
                    'format' => 'INFACT',
                    'json' => 'false'
                ],
            ];

            /* conversion de notre array en fichier xml */
            $pharmagest_array_to_xml = new ArrayToXml($pharmagest_array_final, $xml_root);

            // indentation de notre fichier xml
            $pharmagest_xml = $pharmagest_array_to_xml->prettify()->toXml();

            var_dump($pharmagest_xml);
            $xml_file = 'test.xml';
            $xml_file_path = __DIR__ . '/tmp/' . $xml_file;
            if (!$fp = fopen($xml_file_path, 'w')) {
                echo "Impossible d'ouvrir le fichier ($xml_file_path)\n";
                exit;
            }

            if (fwrite($fp, $pharmagest_xml) === FALSE) {
                echo "Impossible d'écrire dans le fichier ($xml_file_path)\n";
                exit;
            }
            //echo "L'écriture de ($pharmagest_xml) dans le fichier ($xml_file_path) a réussi\n";
            fclose($fp);
            $xml = file_get_contents($xml_file_path);

            if ($type == "VENTE") {

                $retour_curl = $this->offiConnectRequest($url, $page, $login, $pass, $pharmagest_xml);
                echo $retour_curl;
            } else if ($type == "FACINT") {

                $xml_demande = '<?xml version="1.0" encoding="UTF-8"?>
                                    <beldemande date="' . date('Y-m-d') . '" version="1.1" format="REQUEST">
                                    <request type="FACINT" num_pharma="' . $login . '"></request>
                                    </beldemande>';

                $fp = fopen(__DIR__ . '/tmp/facint - ' . $login . '.xml', 'w');
                $retour_curl = $this->offiConnectRequest($url, $page, $login, $pass, $xml_demande);
                var_dump($retour_curl);
                var_dump($xml_demande);
                fwrite($fp, $retour_curl);
                fclose($fp);
            }
        }
    }
}
