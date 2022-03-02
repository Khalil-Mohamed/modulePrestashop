<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class elvetisPacom1 extends Module
{
    public function __construct()
    {
        $this->name = 'elvetispacom1';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Agence Pacom1';
        $this->ps_versions_compliancy = [
            'min' => '1.6',
            'max' => _PS_VERSION_
        ];
        $this->bootstrap = true;
        $this->cron_url = _PS_BASE_URL_._MODULE_DIR_.'elvetisPacom1/cron.php?token='.substr(Tools::encrypt('elvetisPacom1/cron'), 0, 10);
        parent::__construct();

        $this->displayName = $this->l('Gestion erp elvetis');
        $this->description = $this->l('Gestion erp de la société elvetis');

        $this->confirmUninstall = $this->l('etes vous sur de vouloir supprimer le module ??');

        if (!Configuration::get('ELVETIS_PACOM1')) {
            $this->warning = $this->l('Aucun nom trouver');
        }
    }

    public function install()
    {
        return (parent::install()
            && $this->registerHook('leftColumn')
            && $this->registerHook('header')
            && $this->registerHook('actionPaymentConfirmation')
            && Configuration::updateValue('ELVETIS_PACOM1', 'my friend'));
    }

    public function uninstall()
    {
        return (parent::uninstall()
            && Configuration::deleteByName('ELVETIS_PACOM1'));
    }

    //fonction affichant le formulaire
    public function displayForm()
    {
        // formulaire du champ de configuration
        $form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('lien cron : '.$this->cron_url.' '),
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
        $test = "<h2>".$this->cron_url."</h2>";

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->table = $this->table;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&' . http_build_query(['configure' => $this->name]);
        $helper->submit_action = 'submit' . $this->name;

        // langage par defaut
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');

        // chargement de la valeur du formulaire
        $helper->fields_value['PACOM1_CONFIG'] = Tools::getValue('PACOM1_CONFIG', Configuration::get('PACOM1_CONFIG'));

        return $helper->generateForm([$form]);
    }

    // fonction recupérant la valeur de configuration du module
    public function getContent()
    {
        $output = '';

        // on execute si le formulaire est valider
        if (Tools::isSubmit('submit' . $this->name)) {
            // on recupere la valeur entrer par l'utilisateur
            $pacom1_config_value = (string) Tools::getValue('PACOM1_CONFIG');
            // on verifie que la valeur est valide
            if (empty($pacom1_config_value) || !Validate::isGenericName($pacom1_config_value)) {
                // erreur si invalide
                $output = $this->displayError($this->l('Erreur'));
            } else {
                // si valide, on update et affiche un message de confirmation
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
        $remote_file = "private";


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

    public function hookactionPaymentConfirmation(array $params)
    {

        /* recuperation des information du client er produits */
        $id_order = $params['id_order'];
        $order = new Order((int) $id_order);
        $customer = new Customer($order->id_customer);
        $products = $order->getProducts();
        $adresses = new CustomerAddress($order->id_address_delivery);
        $orderCarrier = new OrderCarrier($order->getIdOrderCarrier());
        
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
        $customer_country = $adresses->country;        
        
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
            $product_reference = $product['product_reference'];
            $product_quantity =  $product['product_quantity'];
            $product_unit_HT = round($product['unit_price_tax_excl'], 2);
            $product_unit_TTC = round($product['unit_price_tax_incl'], 2);

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
    }
}

