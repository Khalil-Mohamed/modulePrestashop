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

        $id_order = $params['id_order'];
        $order = new Order((int) $id_order);
        $customer = new Customer($order->id_customer);
        $products = $order->getProducts();
        $adresses = new CustomerAddress($order->id_address_delivery);
        $gender = new Gender($customer->id_gender);

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

        /* declaration et ecriture des titres des colonnes */

        $elvetis_column_title = array(
            'Nom du client', 'Adresse 1', 'Adresse2', 'Code postal', 'Ville', 'Adresse email',
            'Téléphone 1', 'Téléphone 2', 'Numéro de facture', 'Montant Total HT de la commande', 'Montant Total HT TVA de la commande', 'Montant TTC de la commande', 'Identifiant relais colis',
            'Mode de paiement', 'Pays ', 'Code transport', 'Filler', 'Remise coupon', 'Adresse complémentaire', 'Commentaire sur transport', 'S = livraison le Samedi', 'Code produit Colissimo',
            'Code postal Export', 'N° téléphone Export', 'CIP (code produit)', 'Quantité', 'Prix unitaire HT', 'Prix unitaire TTC'
        );


        foreach ($products as $product) {

            /* information produit */
            $product_id = $product['id_product'];
            $product_name = $product['product_name'];
            $product_reference = $product['product_reference'];
            $product_quantity =  $product['product_quantity'];
            $product_unit_HT = round($product['unit_price_tax_excl'], 2);
            $product_unit_TTC = round($product['unit_price_tax_incl'], 2);
            $tax = $product["tax_rate"];

            /* recuperation de l'isbn parent */
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

                


                /* remplissage de valeur vide par un epsace pour garder toute les colonnesdu tabelau*/
                $first_keys = array_key_first($elvetis_array_temp);
                $last_keys = max(array_keys($elvetis_array_temp));

                for ($i = $first_keys; $i <= $last_keys; $i++) {
                    if (!isset($elvetis_array_temp[$i])) {
                        $elvetis_array_temp[$i] = "";
                    }
                }
                /* ordonner le tableau*/
                echo "<pre>";
                print_r($elvetis_array_temp);
                echo "</pre>";
                ksort($elvetis_array_temp);

                $elvetis_array_final[] = array_combine($elvetis_column_title, $elvetis_array_temp);

            } 
            else if ($product_isbn == 2) {
            
                $pharmagest_array_final[] = [
                    'lignevente' => [
                        '_attributes' => ['numero_lignevente' => $product_id],
                        'codeproduit' => $product_reference,
                        'designation_produit' => [
                            '_cdata' => $product_name,
                        ],
                        'quantite' => $product_quantity,
                        'remise' => 'z',
                        'prix_brut' => $product_unit_TTC,
                        'prix_net' => $product_unit_HT,
                        'tauxtva' => $tax,
                    ]
                ];
                echo "<pre>";
                print_r($pharmagest_array_final);
                echo "</pre>";
            }        
            /*if (!empty($pharmagest_array_final)) {
                $exoneration_tva = 0;
                if ($tax == 0) : $exoneration_tva = 1;
                endif;
                $test2 = [
                    'beldemande' => [
                        '_atributes' => ['version' => '1.6', 'date' => $sale_date, 'format' => 'INFACT', 'json' => 'false'],
                        'infact' => [
                            'vente' => [
                                '_atributes' => ['num_pharma' => 'testss2i', 'numero_vente' => $bills_id],
                                'client' => [
                                    '_atributes' => ['client' => $customer_id],
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
                                'montant_port_ht' => $bills_total_HT,
                                'tauxtva_port' => $tax,
                                'total_ttc' => $bills_total_TTC,
                                'exoneration_tva' => $exoneration_tva,
                                $pharmagest_array_final,
                            ]
                        ]
                    ]
                ];
                function array_to_xml($data, &$xml_data, $bills_id)
                {
                    foreach ($data as $key => $value) {
                        if (is_array($value)) {
                            if (is_numeric($key)) {
                                $key = 'item' . $key;
                            }
                            if ($key == "vente") {
                                $subnode = $xml_data->addChild($key);
                                $subnode->addAttribute('num_pharma','testss2i');
                                $subnode->addAttribute('numero_vente',''.$bills_id.'');
                                array_to_xml($value, $subnode, $bills_id);
                            }
                            else{
                                $subnode = $xml_data->addChild($key);
                                array_to_xml($value, $subnode, $bills_id);
                            }
                        } else {
                            $xml_data->addChild("$key", htmlspecialchars("$value"));
                        }
                    }
                }
                $pharmagest_xml = new SimpleXMLElement('<?xml version="1.0"?><beldemande version="1.6" date="'.$sale_date.'" format="INFACT" json="false"></beldemande>');
                array_to_xml($pharmagest, $pharmagest_xml, $bills_id);
                $xmlfile = ArrayToXml::convert($test2);
            }*/
        }
        if (!empty($elvetis_array_final)) {
            $csvfile = __DIR__ . '/tmp/CDE_' . $bills_id . '.csv';
            $file = fopen($csvfile, 'w');
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, $elvetis_column_title, ";");
            foreach ($elvetis_array_final as $line => $value) {
                fputcsv($file, $elvetis_array_final[$line], ";");
            }
            fclose($file);
        }
        if(!empty($pharmagest_array_final))
        {
            
        }
        die;
    }
}
