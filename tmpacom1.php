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

    public function hookactionPaymentConfirmation(array $params)
    {
        /* recuperation des information du client er produits */
        $id_order = $params['id_order'];
        $order = new Order((int) $id_order);
        $customer = new Customer($order->id_customer);
        $products = $order->getProducts();
        $adresses = new CustomerAddress($order->id_address_delivery);
        $gender = new Gender($customer->id_gender);

        echo "<pre>";
        print_r($order);
        echo "<pre>";

        echo "<pre>";
        print_r($customer);
        echo "<pre>";

        echo "<pre>";
        print_r($products);
        echo "<pre>";

        echo "<pre>";
        print_r($adresses);
        echo "<pre>";

        echo "<pre>";
        print_r($gender);
        echo "<pre>";

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
        $customer_birth = $customer->birthday;
        $customer_country = $adresses->country;
        $customer_gender = $gender->name[1];

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
                    'remise' => 0,
                    'prix_brut' => $product_unit_HT,
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

            $csv_file_name = 'CDE_' . $bills_id . '.csv';
            $csvfile = __DIR__ . '/tmp/' . $csv_file_name;
            $file = fopen($csvfile, 'w');
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, $elvetis_column_title, ";");
            foreach ($elvetis_array_final as $line => $value) {
                fputcsv($file, $elvetis_array_final[$line], ";");
            }

            $file = fopen($csvfile, 'r');

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
                if (ftp_fput($conn_id, $remote_file . '/' . $csv_file_name, $file, FTP_ASCII)) {
                    echo "Chargement avec succès du fichier $file\n";
                } else {
                    echo "Il y a eu un problème lors du chargement du fichier $file\n";
                }
            }
            fclose($file);
            ftp_close($conn_id);
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
                            'num_pharma' => 'testss2i',
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
            $pharmagest_array_final = array_merge($pharmagest_array_final['infact']['vente'], $pharmagest_array_product);

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

            /* ecriture de notre xml dans un fichier xml */
            $filename = __DIR__ . '/tmp/test.xml';
            if (is_writable($filename)) {

                if (!$fp = fopen($filename, 'a')) {
                    echo "Impossible d'ouvrir le fichier ($filename)";
                    exit;
                }

                if (fwrite($fp, $pharmagest_xml) === FALSE) {
                    echo "Impossible d'écrire dans le fichier ($filename)";
                    exit;
                }
                echo "L'écriture de ($pharmagest_xml) dans le fichier ($filename) a réussi";
                fclose($fp);
            } else {
                echo "Le fichier $filename n'est pas accessible en écriture.";
            }
            die;
        }
    }
}
