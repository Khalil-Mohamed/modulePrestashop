<?php

use PhpParser\Node\Stmt\ElseIf_;
use PrestaShop\PrestaShop\Adapter\Entity\CustomerAddress;

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
        $bills_total_TTC = round($order->total_paid_real, 2);
        $sale_date = $order->date_add;

        /* declaration et ecriture des titres des colonnes */

        $tmp_column_title = array(
            'Nom du client', 'Adresse 1', 'Adresse2', 'Code postal', 'Ville', 'Adresse email',
            'Téléphone 1', 'Téléphone 2', 'Numéro de facture', 'Montant Total HT de la commande', 'Montant Total HT TVA de la commande', 'Montant TTC de la commande', 'Identifiant relais colis',
            'Mode de paiement', 'Pays ', 'Code transport', 'Filler', 'Remise coupon', 'Adresse complémentaire', 'Commentaire sur transport', 'S = livraison le Samedi', 'Code produit Colissimo',
            'Code postal Export', 'N° téléphone Export', 'CIP (code produit)', 'Quantité', 'Prix unitaire HT', 'Prix unitaire TTC'
        );

        $pharmatest_column_title = array(
            'nom', 'prenom', 'Adresse2', 'Code postal', 'Ville', 'Adresse email',
            'Téléphone 1', 'Téléphone 2', 'Numéro de facture', 'Montant Total HT de la commande', 'Montant Total HT TVA de la commande', 'Montant TTC de la commande', 'Identifiant relais colis',
            'Mode de paiement', 'Pays ', 'Code transport', 'Filler', 'Remise coupon', 'Adresse complémentaire', 'Commentaire sur transport', 'S = livraison le Samedi', 'Code produit Colissimo',
            'Code postal Export', 'N° téléphone Export', 'CIP (code produit)', 'Quantité', 'Prix unitaire HT', 'Prix unitaire TTC'
        );

        foreach ($products as $product) {

            /* information produit */
            $product_name = $product['product_name'];
            $product_reference = $product['product_reference'];
            $product_quantity =  $product['product_quantity'];
            $product_unit_HT = round($product['unit_price_tax_excl'], 2);
            $product_unit_TTC = round($product['unit_price_tax_incl'], 2);

            /* recuperation de l'isbn parent */
            $product_isbn = $product['isbn'];

            /* la valeur 1 correspond a l'organisme eveltis */
            if ($product_isbn == 1) {
                /* remplissage des infos de la commande dans un tableau temporaire */
                $tmp_array_temp = array();

                $tmp_array_temp[array_search('Nom du client', $tmp_column_title)] = $customer_fullname;
                $tmp_array_temp[array_search('Adresse 1', $tmp_column_title)] = $customer_adress1;
                $tmp_array_temp[array_search('Adresse2', $tmp_column_title)] = $customer_adress2;
                $tmp_array_temp[array_search('Code postal', $tmp_column_title)] = $customer_postcode;
                $tmp_array_temp[array_search('Ville', $tmp_column_title)] = $customer_city;
                $tmp_array_temp[array_search('Adresse email', $tmp_column_title)] = $customer_email;
                $tmp_array_temp[array_search('Téléphone 1', $tmp_column_title)] = $customer_phone1;
                $tmp_array_temp[array_search('Téléphone 2', $tmp_column_title)] = $customer_phone2;

                $tmp_array_temp[array_search('Numéro de facture', $tmp_column_title)] = $bills_id;
                $tmp_array_temp[array_search('Montant Total HT de la commande', $tmp_column_title)] = $bills_total_HT;
                $tmp_array_temp[array_search('Montant Total HT TVA de la commande', $tmp_column_title)] = $bills_total_TVA;
                $tmp_array_temp[array_search('Montant TTC de la commande', $tmp_column_title)] = $bills_total_TTC;

                $tmp_array_temp[array_search('CIP (code produit)', $tmp_column_title)] = $product_reference;
                $tmp_array_temp[array_search('Quantité', $tmp_column_title)] = $product_quantity;
                $tmp_array_temp[array_search('Prix unitaire HT', $tmp_column_title)] = $product_unit_HT;
                $tmp_array_temp[array_search('Prix unitaire TTC', $tmp_column_title)] = $product_unit_TTC;


                /* remplissage de valeur vide par un epsace pour garder toute les colonnesdu tabelau*/
                $first_keys = array_key_first($tmp_array_temp);
                $last_keys = max(array_keys($tmp_array_temp));

                for ($i = $first_keys; $i <= $last_keys; $i++) {
                    if (!isset($tmp_array_temp[$i])) {
                        $tmp_array_temp[$i] = "";
                    } else {
                    }
                }

                /* ordonner le tableau*/
                ksort($tmp_array_temp);

                /* ecriture dans notre fichier.csv */
                $tmp_array_final = array_combine($tmp_column_title, $tmp_array_temp);
            }
            else if ($product_isbn == 2)
            {
                    $lignevente = array(
                        "codeproduit" => $product_reference,
                        "designation_produit" => $product_name,
                        "quantite" => $product_quantity,
                        "remise" => "z",
                        "prix_brut" => $product_unit_TTC,
                        "prix_brut" => $product_unit_HT,
                        "tauxtva" => "z",
                    );
                
            }
            if (isset($tmp_array_final)) {
                $csvfile = fopen(__DIR__ . '/tmp/CDE_' . $bills_id . '.csv', 'w');
                fwrite($csvfile, "\xEF\xBB\xBF");
        
                fputcsv($csvfile, $tmp_column_title, ";");
                fputcsv($csvfile, $tmp_array_final, ";");
            }
        }
        


        echo "<pre>";
        print_r($order);
        echo "</pre>";

        echo "<pre>";
        print_r($customer);
        echo "</pre>";

        echo "<pre>";
        print_r($adresses);
        echo "</pre>";

        echo "<pre>";
        print_r($gender);
        echo "</pre>";

        echo "<pre>";
        print_r($product);
        echo "</pre>";
        die;
    }
}
