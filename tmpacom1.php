<?php

use PrestaShop\PrestaShop\Adapter\Entity\CustomerAddress;
use Spatie\ArrayToXml\ArrayToXml;


if (!defined('_PS_VERSION_'))
    exit;

class tmpacom1 extends Module
{
    public function __construct()
    {
        $this->name = 'tmpacom1';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'pacom1';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7',
            'max' => _PS_VERSION_
        ];
        $this->bootstrap = true;
        parent::__construct();
        $this->displayName = $this->l('envoie de produit vers erp');
        $this->description = $this->l('recupere la liste de produit apres validation d\'une commande.');

        $this->confirmUninstall = $this->l('confirmer votre choix ?');

        if (!Configuration::get('MYMODULE_NAME')) {
            $this->warning = $this->l('aucun nom trouver');
        }
    }

    public function install()
    {
        return (parent::install()
            && $this->registerHook('leftColumn')
            && $this->registerHook('header')
            && $this->registerHook('actionPaymentConfirmation')
            && Configuration::updateValue('MYMODULE_NAME', 'my friend'));
    }

    public function uninstall()
    {
        return (parent::uninstall()
            && Configuration::deleteByName('MYMODULE_NAME'));
    }

    public function displayForm()
    {
        // formulaire du champ de configuration
        $form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Quantité minimum pour afficher un produit'),
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Entrez votre nombre minimum d\'article : '),
                        'name' => 'PACOM1_CONFIG',
                        'size' => 20,
                        'required' => true,
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Enregistrer'),
                    'class' => 'btn btn-default pull-right',
                ],
            ],
        ];

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->table = $this->table;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&' . http_build_query(['configure' => $this->name]);
        $helper->submit_action = 'submit' . $this->name;

        // Default language
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');

        // Load current value into the form
        $helper->fields_value['PACOM1_CONFIG'] = Tools::getValue('PACOM1_CONFIG', Configuration::get('PACOM1_CONFIG'));

        return $helper->generateForm([$form]);
    }

    public function getContent()
    {
        $output = '';

        // this part is executed only when the form is submitted
        if (Tools::isSubmit('submit' . $this->name)) {
            // retrieve the value set by the user
            $pacom1_config_value = (string) Tools::getValue('PACOM1_CONFIG');
            // check that the value is valid
            if (empty($pacom1_config_value) || !Validate::isGenericName($pacom1_config_value)) {
                // invalid value, show an error
                $output = $this->displayError($this->l('Erreur'));
            } else {
                // value is ok, update it and display a confirmation message
                Configuration::updateValue('PACOM1_CONFIG', $pacom1_config_value);
                $output = $this->displayConfirmation($this->l('Valeur enregistrer !'));
            }
        }

        // display any message, then the form
        return $output . $this->displayForm();
    }

    public function elvetisSendOrder($csv, $name)
    {
        /* passage en mode lecture du csv */
        $file = fopen($csv, 'r');

        // identifiant de connection ftp
        $ftp_user_name = "pacom1_tsn_ftp";
        $ftp_user_pass = "qf3FXpxM_";
        $ftp_server = "s3.pacom1.com";
        $remote_file = "private";
        $conn_id = ftp_ssl_connect($ftp_server);

        // connection ftp
        $login_result = ftp_login($conn_id, $ftp_user_name, $ftp_user_pass);
        ftp_pasv($conn_id, true);

        // ont verifie la connection
        if ((!$conn_id) || (!$login_result)) {
            echo "Connexion ftp échouer";
            echo "tentative de connection de  $ftp_server vers l'utilisateur $ftp_user_name";
            exit;
        } else {
            echo "Connecter vers $ftp_server, pour l'utilisateur $ftp_user_name\n";
            if (ftp_fput($conn_id, $remote_file . '/' . $name, $file, FTP_ASCII)) {
                echo "Chargement avec succès du fichier $file\n";
            } else {
                echo "Il y a eu un problème lors du chargement du fichier $file\n";
            }
        }

        /* fermeture du fichier et de la connexion ftp */
        fclose($file);
        ftp_close($conn_id);
    }
    
    public function elvetisStock()
    {
        Module::getInstanceByName('WkCombinationcustomize');
        /* je recupere la valeur par defaut de limite de stock */
        $pacom1_config_value = intval(Configuration::get('PACOM1_CONFIG'));
        var_dump($pacom1_config_value);

        /* info des fichier local/serveur ftp */
        $local_file_name = 'stocks.csv';
        $local_file = __DIR__ . '/tmp/' . $local_file_name;
        $ftp_file = "stocks.csv";
        $remote_file = "private";

        // identifiant de connection ftp
        $ftp_user_name = "pacom1_tsn_ftp";
        $ftp_user_pass = "qf3FXpxM_";
        $ftp_server = "s3.pacom1.com";
        $conn_id = ftp_ssl_connect($ftp_server);

        // connection ftp
        $login_result = ftp_login($conn_id, $ftp_user_name, $ftp_user_pass);
        ftp_pasv($conn_id, true);

        if ((!$conn_id) || (!$login_result)) {
            echo "Connexion ftp échouer";
            echo "tentative de connection de  $ftp_server vers l'utilisateur $ftp_user_name";
            exit;
        } else {
            echo "Connecter vers $ftp_server, pour l'utilisateur $ftp_user_name\n";
        }
        // recuperarion du csv depuis le ftp //
        if (ftp_get($conn_id, $local_file, $remote_file . '/' . $ftp_file, FTP_BINARY)) {
            echo "Le fichier $local_file a été écrit avec succès\n";
        } else {
            echo "Il y a un problème\n";
        }
        ftp_close($conn_id);

        $row = -1;
        if (($handle = fopen($local_file, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
                $num = count($data);
                $row++;
                for ($c = 0; $c < $num; $c++) {
                    $arr[$row][$c] = $data[$c];
                }
            }
            fclose($handle);
        }

        foreach ($arr as $keys => $value) :
            $all_reference[] = Product::getIdByReference($arr[$keys][0]);
            $product = new Product($all_reference);
            $reference = $product->reference;
            $attribute = $product->getAttributeCombinations();
            $id_attribut = $attribute[$keys]['id_product_attribute'];
            $attribute_reference = $attribute[$keys]['reference'];

            if ($arr[$keys][0] == $attribute_reference) :
                echo "ok declinaison\n";
                if (intval($arr[$keys][2]) < $pacom1_config_value) :
                    echo "inferieur a pacom1\n";
                    $combiData = WkCombinationStatus::getCombinationStatus(
                        $product->id,
                        $id_attribut,
                        $product->id_shop_default
                    );

                    if (!$combiData) :

                        $objCombiStatus = new WkCombinationStatus();
                        $objCombiStatus->id_ps_product = (int) $product->id;
                        $objCombiStatus->id_ps_product_attribute = (int) $id_attribut;
                        $objCombiStatus->id_shop = $product->id_shop_default;
                        $objCombiStatus->save();
                    endif;
                else :
                    echo "superieur a pacom1\n";
                    $enable = WkCombinationStatus::deleteORActiveSinglePsCombination($id_attribut);
                endif;
            endif;

            if ($arr[$keys][0] == $reference) :
                echo "ok normal";
                if (intval($arr[$keys][2]) < $pacom1_config_value) :
                    echo "inferieur a pacom1\n";
                    $product->active = 0;
                    $product->update();
                else :
                    echo "superieur a pacom1\n";
                    $product->active = 1;
                    $product->update();
                endif;
            endif;

        endforeach;
    }

    public function pharmagestStock()
    {
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

        $json_file_name = 'stock - testss2i.json';
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
            $all_id= Product::getIdByReference($value);            
            if(!empty($all_id)):
                /*echo($value);
                echo "<pre>";
                print_r($all_id);
                echo "<pre>";*/
                $pharmagest_product = new Product($all_id);
                $reference = $pharmagest_product->reference;
                /*echo "<pre>";
                print_r($pharmagest_product);
                echo "<pre>";*/
                if ($value == $reference) :
                    echo "ok normal";
                    if (intval($stock_produit[$key]) < $mini_produit[$key]) :
                        echo "inferieur a pacom1\n";
                        $pharmagest_product->active = 0;
                        $pharmagest_product->update();
                    else :
                        echo "superieur a pacom1\n";
                        $pharmagest_product->active = 1;
                        $pharmagest_product->update();
                    endif;
                endif;
            endif;
        }
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
        echo "<pre>";
        print_r($orderCarrier);
        echo"</pre";

        // paramettre officonnect //
        $type = "VENTE"; // STOCK / VENTE / FACINT / PROMO
        $url = 'https://officonnect.pharmagest.com/';
        $page = 'impexboutique.php';
        $login = 'testss2i';
        $pass = 'D0n46j0A';

        /* information client */
        $customer_lastname = $customer->lastname;
        $customer_firstname = $customer->firstname;
        $array_name = array($customer_firstname, ' ', $customer_lastname);
        $customer_id = $order->id_customer;
        $customer_fullname = join($array_name);
        $customer_adress1 = $adresses->address1;
        $customer_adress2 = $adresses->address2;
        $customer_postcode = $adresses->postcode;
        $customer_city = $adresses->city;
        $customer_email = $customer->email;
        $customer_phone1 = $adresses->phone;
        $customer_phone2 = $adresses->phone_mobile;
        echo($customer_phone2);
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

        /* declaration et ecriture des titres des colonnes pour elvetis */

        $elvetis_column_title = array(
            'Nom du client', 'Adresse 1', 'Adresse2', 'Code postal', 'Ville', 'Adresse email',
            'Téléphone 1', 'Téléphone 2', 'Numéro de facture', 'Montant Total HT de la commande', 'Montant Total HT TVA de la commande', 'Montant TTC de la commande', 'Identifiant relais colis',
            'Mode de paiement', 'Pays ', 'Code transport', 'Filler', 'Remise coupon', 'Adresse complémentaire', 'Commentaire sur transport', 'S = livraison le Samedi', 'Code produit Colissimo',
            'Code postal Export', 'N° téléphone Export', 'CIP (code produit)', 'Quantité', 'Prix unitaire HT', 'Prix unitaire TTC'
        );

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
            if ($product_isbn == 1) {
                /* remplissage des infos de la commande dans un tableau temporaire */
                $elvetis_array_temp = array();

                $elvetis_array_temp[array_search('Nom du client', $elvetis_column_title)] = $customer_fullname;
                $elvetis_array_temp[array_search('Adresse 1', $elvetis_column_title)] = $customer_adress1;
                $elvetis_array_temp[array_search('Adresse2', $elvetis_column_title)] = $customer_adress2;
                $elvetis_array_temp[array_search('Code postal', $elvetis_column_title)] = $customer_postcode;
                $elvetis_array_temp[array_search('Ville', $elvetis_column_title)] = $customer_city;
                $elvetis_array_temp[array_search('Adresse email', $elvetis_column_title)] = $customer_email;
                $elvetis_array_temp[array_search('Téléphone 1', $elvetis_column_title)] = $customer_phone1;
                $elvetis_array_temp[array_search('Téléphone 2', $elvetis_column_title)] = $customer_phone2;

                $elvetis_array_temp[array_search('Numéro de facture', $elvetis_column_title)] = $bills_id;
                $elvetis_array_temp[array_search('Montant Total HT de la commande', $elvetis_column_title)] = $bills_total_HT;
                $elvetis_array_temp[array_search('Montant Total HT TVA de la commande', $elvetis_column_title)] = $bills_total_TVA;
                $elvetis_array_temp[array_search('Montant TTC de la commande', $elvetis_column_title)] = $bills_total_TTC;

                $elvetis_array_temp[array_search('CIP (code produit)', $elvetis_column_title)] = $product_reference;
                $elvetis_array_temp[array_search('Quantité', $elvetis_column_title)] = $product_quantity;
                $elvetis_array_temp[array_search('Prix unitaire HT', $elvetis_column_title)] = $product_unit_HT;
                $elvetis_array_temp[array_search('Prix unitaire TTC', $elvetis_column_title)] = $product_unit_TTC;




                /* remplissage de valeur vide par un epsace pour garder toute les colonnes du tabelau*/
                $first_keys = array_key_first($elvetis_array_temp);
                $last_keys = max(array_keys($elvetis_array_temp));

                for ($i = $first_keys; $i <= $last_keys; $i++) {
                    if (!isset($elvetis_array_temp[$i])) {
                        $elvetis_array_temp[$i] = "";
                    }
                }

                /* ordonner le tableau*/
                ksort($elvetis_array_temp);

                $elvetis_array_final[] = array_combine($elvetis_column_title, $elvetis_array_temp);
            } else if ($product_isbn == 2) {

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

        /* si le tableau elvetis n'est pas vide on ecrit dans un fichier csv */
        if (!empty($elvetis_array_final)) {

            /* ecriture dans notre csv */
            $csv_file_name = 'CDE_' . $bills_id . '.csv';
            $csvfile = __DIR__ . '/tmp/' . $csv_file_name;
            $file = fopen($csvfile, 'w');
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, $elvetis_column_title, ";");
            foreach ($elvetis_array_final as $line => $value) {
                fputcsv($file, $elvetis_array_final[$line], ";");
            }
            $this->elvetisSendOrder($csvfile, $csv_file_name);
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

            $xml_file_name = __DIR__ . '/tmp/test.xml';
            if (is_writable($xml_file_name)) {

                if (!$fp = fopen($xml_file_name, 'w')) {
                    echo "Impossible d'ouvrir le fichier ($xml_file_name)\n";
                    exit;
                }

                if (fwrite($fp, $pharmagest_xml) === FALSE) {
                    echo "Impossible d'écrire dans le fichier ($xml_file_name)\n";
                    exit;
                }
                //echo "L'écriture de ($pharmagest_xml) dans le fichier ($xml_file_name) a réussi\n";
                fclose($fp);
            } else {
                echo "Le fichier $xml_file_name n'est pas accessible en écriture.\n";
            }
            $xml = file_get_contents($xml_file_name);
            
            if ($type == "VENTE") {

                $retour_curl = $this->offiConnectRequest($url, $page, $login, $pass, $pharmagest_xml);
                echo $retour_curl;
            }
            else if ($type == "FACINT") {
	
                $xml_demande = '<?xml version="1.0" encoding="UTF-8"?>
                                    <beldemande date="' . date('Y-m-d') . '" version="1.1" format="REQUEST">
                                    <request type="FACINT" num_pharma="'.$login.'"></request>
                                    </beldemande>';	
                    
                $fp = fopen(__DIR__ . '/tmp/facint - '.$login.'.xml', 'w');
                $retour_curl = $this->offiConnectRequest($url , $page , $login, $pass, $xml_demande);
                var_dump($retour_curl);
                var_dump($xml_demande);
                fwrite($fp,$retour_curl);
                fclose($fp);	
            }
            //$this->elvetisStock();
            $this->pharmagestStock();
            die;
        }
    }
}
