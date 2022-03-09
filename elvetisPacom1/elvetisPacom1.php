<?php
include_once _PS_MODULE_DIR_ . 'multitrackingbo/controllers/admin/AdminMultiTrackingBo.php';

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
        $this->cron_url = _PS_BASE_URL_ . _MODULE_DIR_ . 'elvetisPacom1/cron.php?token=' . substr(Tools::encrypt('elvetisPacom1/cron'), 0, 10);
        parent::__construct();

        $this->displayName = $this->l('Gestion erp elvetis');
        $this->description = $this->l('Gestion erp de la société elvetis');

        $this->confirmUninstall = $this->l('etes vous sur de vouloir supprimer le module ??');

        if (!Configuration::get('ELVETIS_PACOM1')) :
            $this->warning = $this->l('Aucun nom trouver');
        endif;
    }

    // fonction pour installer le module et les hook necessaires 
    public function install()
    {
        return (parent::install()
            && $this->registerHook('leftColumn')
            && $this->registerHook('header')
            && $this->registerHook('actionPaymentConfirmation')
            && Configuration::updateValue('ELVETIS_PACOM1', 'my friend'));
    }

    //fonction pour desinstaller le module
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
                    'title' => $this->l('lien cron : ' . $this->cron_url . ' '),
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
        if (Tools::isSubmit('submit' . $this->name)) :
            // on recupere la valeur entrer par l'utilisateur
            $pacom1_config_value = (string) Tools::getValue('PACOM1_CONFIG');
            // on verifie que la valeur est valide
            if (empty($pacom1_config_value) || !Validate::isGenericName($pacom1_config_value)) :
                // erreur si invalide
                $output = $this->displayError($this->l('Erreur'));
            else :
                // si valide, on update et affiche un message de confirmation
                Configuration::updateValue('PACOM1_CONFIG', $pacom1_config_value);
                $output = $this->displayConfirmation($this->l('Valeur enregistrer !'));
            endif;
        endif;

        // display any message, then the form
        return $output . $this->displayForm();
    }

    // fonction pour se connecter au serveur ftp
    public function ftpConnection()
    {
        // identifiant de connection ftp
        $ftp_user_name = "pacom1_tsn_ftp";
        $ftp_user_pass = "qf3FXpxM_";
        $ftp_server = "s3.pacom1.com";
        $conn_id = ftp_ssl_connect($ftp_server);
        // connection ftp
        $login_result = ftp_login($conn_id, $ftp_user_name, $ftp_user_pass);
        ftp_pasv($conn_id, true);

        if ((!$conn_id) || (!$login_result)) :
            echo "Connexion ftp échouer";
            echo "tentative de connection de  $ftp_server vers l'utilisateur $ftp_user_name";
            exit;
        else :
            echo "Connecter vers $ftp_server, pour l'utilisateur $ftp_user_name\n";
        endif;
        return $conn_id;
    }

    // fonction qui recupere les fichier dans le ftp elvetis
    public function ftpGetFile()
    {
        $local_file = __DIR__ . '\tmp';
        $remote_file = "private";

        $conn_id = $this->ftpConnection();

        $contents = ftp_nlist($conn_id, $remote_file);

        foreach ($contents as $file) :
            $local_path = __DIR__ . '/tmp/';
            $local_file = __DIR__ . '/tmp/' . $file;
            if (str_contains($file, 'stocks')) :
                ftp_get($conn_id, $local_file, $remote_file . '/' . $file, FTP_BINARY);

            elseif (str_contains($file, date('ymd'))) :
                ftp_get($conn_id, $local_file, $remote_file . '/' . $file, FTP_BINARY);
                $file = rename($local_file, $local_path . 'EXP_' . date('ymd') . '.csv');

            endif;
        endforeach;
        ftp_close($conn_id);
    }

    // fonction qui stock les données fichier dans un tablleau 
    public function stockInArray($local_file)
    {
        $row = -1;
        if (($handle = fopen($local_file, "r")) !== FALSE) :
            while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) :
                $num = count($data);
                $row++;
                for ($c = 0; $c < $num; $c++) :
                    $arr[$row][$c] = $data[$c];
                endfor;
            endwhile;
            // ont supprime la premier ligne
            unset($arr[0]);
            fclose($handle);
        endif;
        return $arr;
    }

    // fonction pour envoyer la commande via ftp
    public function elvetisSendOrder($csv, $name)
    {
        /* passage en mode lecture du csv */
        $file = fopen($csv, 'r');
        $remote_file = "private";

        $conn_id = $this->ftpConnection();

        if (ftp_fput($conn_id, $remote_file . '/' . $name, $file, FTP_ASCII)) :
            echo "Chargement avec succès du fichier $file\n";
        else:
            echo "Il y a eu un problème lors du chargement du fichier $file\n";
        endif;

        /* fermeture du fichier et de la connexion ftp */
        fclose($file);
        ftp_close($conn_id);
    }

    // fonction pour mettre a jour le stock
    public function elvetisStock()
    {
        // je fait une instance du module WkCombinationcustomize
        Module::getInstanceByName('WkCombinationcustomize');
        /* je recupere la valeur par defaut de limite de stock */
        $pacom1_config_value = intval(Configuration::get('PACOM1_CONFIG'));
        //var_dump($pacom1_config_value);

        $stock_file = 'stocks.csv';
        $csvfile = __DIR__ . '/tmp/' . $stock_file;

        $arr = $this->stockInArray($csvfile);
        var_dump($arr);

        /*nous allons verifier dans cette boucle le stock de chaque produit */
        foreach ($arr as $keys => $value) :
            $all_id[] = Product::getIdByReference($arr[$keys][0]);
            $product = new Product($all_id);
            $reference = $product->reference;
            $attribute = $product->getAttributeCombinations();
            $id_attribut = $attribute[$keys]['id_product_attribute'];
            $attribute_reference = $attribute[$keys]['reference'];

            // on verifie si nous sommes dans une declinaison
            if ($arr[$keys][0] == $attribute_reference) :
                echo "ce produit est une declinaison\n";
                if (intval($arr[$keys][2]) < $pacom1_config_value) :
                    echo "quantitté minimum insufisante\n";

                    // on recupere les info de la declinaison dans la database
                    $combiData = WkCombinationStatus::getCombinationStatus(
                        $product->id,
                        $id_attribut,
                        $product->id_shop_default
                    );
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

            // on verfier si c'est un produit mere
            if ($arr[$keys][0] == $reference) :
                echo "le produit est un produit mere";
                if (intval($arr[$keys][2]) < $pacom1_config_value) :
                    echo "quantitté minimum insufisante\n";
                    $product->active = 0;
                    $product->update();
                else :
                    echo "quantitté minimum suffisante\n";
                    $product->active = 1;
                    $product->update();
                endif;
            endif;

        endforeach;
    }

    // fonction pour gérer le tracking des commandes
    public function elvetisTracking()
    {
        // info du dossier ftp
        $local_file_name = 'EXP_' . date('ymd') . '.csv';
        $local_file = __DIR__ . '/tmp/' . $local_file_name;

        $arr = $this->stockInArray($local_file);

        // ont traite chaque commande dans cette boucle 
        foreach ($arr as $keys => $value) :

            // ont recupere l'id de la commande 
            $all_id_orders = $arr[$keys][0];

            $order_carrier = new OrderCarrier();
            $order = new Order($all_id_orders);
            $products = $order->getProducts();
            $id_carrier = $order->id_carrier;

            // ont stock les infos de suivi
            $order_carrier->id_order = $all_id_orders;
            $order_carrier->id_carrier = $id_carrier;
            $order_carrier->id_order_invoice = 0;
            $order_carrier->shipping_cost_tax_excl = 0;
            $order_carrier->shipping_cost_tax_incl = 0;
            $order_carrier->tracking_number = $arr[$keys][4];
            $id_order_carrier = 0;

            // tableau des produits
            $id_products = array();
            // tableau de notre requete sql
            $sql = array();

            // boucle traitant chaque produit
            foreach ($products as $product) :
                $product_isbn = $product['isbn'];

                // ont verifie que le produit appartient a elvetis 
                if ($product_isbn == 1) :


                    // ont stocke les infos du produit
                    $id_product = (int)$product['product_id'];
                    $id_product_attribute = (int)$product['product_attribute_id'];
                    $quantity = $product['product_quantity'];

                    if ($quantity > 0) :
                        // on verifie si le produit n'est pas deja dans un suivi
                        $has_no_duplicate = MTB::hasNoDuplicate(
                            $id_order_carrier,
                            $all_id_orders,
                            $products,
                            array(
                                'id_product' => $id_product,
                                'id_product_attribute' => $id_product_attribute,
                                'quantity' => $quantity
                            )
                        );

                        if (!$has_no_duplicate) :
                            die(json_encode(array(
                                'success' => 0,
                                'text' => $this->l('A product is duplicated') . ' (' . $product['product_name'] . ')'
                            )));
                        endif;

                        $sql[] = array(
                            'id_product' => $id_product,
                            'id_product_attribute' => $id_product_attribute,
                            'quantity' => $quantity
                        );
                        for ($i = 0; $i < $quantity; $i++) :
                            $id_products[] = $id_product . ';' . $id_product_attribute;
                        endfor;
                    endif;
                endif;
            endforeach;

            // ont verifie si le suivi contient des produits
            if (empty($id_products)) :
                die(json_encode(array(
                    'success' => 0,
                    'text' => $this->l('Please add at least one product.')
                )));
            endif;

            // ajout dans la base de données
            try {
                $order_carrier->add();
            } catch (Exception $e) {
                die(json_encode(array(
                    'success' => 0,
                    'text' => $e->getMessage()
                )));
            }

            $id_order_carrier = (int)$order_carrier->id;

            foreach ($sql as $query) :
                $query['id_order_carrier'] = $id_order_carrier;
                Db::getInstance()->insert('multitrackingbo_products', $query);
            endforeach;

            $order_carrier->weight = MTB::getTotalWeight($id_products);

            try {
                $order_carrier->update();
            } catch (Exception $e) {
                die(json_encode(array(
                    'success' => 0,
                    'text' => $e->getMessage()
                )));
            }
            if (!MTB::refreshShippingCost($order, $order_carrier)) :
                die(json_encode(array(
                    'success' => 0,
                    'text' => $this->l('An error has occurred.')
                )));
            endif;

            $mail = new AdminMultiTrackingBoController();

            if (Tools::getValue('send_mail')) :
                if (!$mail->sendInTransitEmail($order_carrier, $order)) :
                    die(json_encode(array(
                        'success' => 0,
                        'text' => $this->l('Failed to send the email.')
                    )));
                endif;
            endif;

            json_encode(array(
                'success' => 1
            ));

        endforeach;
    }

    // action de notre module lors d'un paiment accepter
    public function hookactionPaymentConfirmation(array $params)
    {

        /* recuperation des information du client er produits */
        $id_order = $params['id_order'];
        $order = new Order((int) $id_order);
        $customer = new Customer($order->id_customer);
        $products = $order->getProducts();
        $adresses = new CustomerAddress($order->id_address_delivery);

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

        /* information facture */
        $bills_id = str_pad($id_order, 7, '0', STR_PAD_LEFT);
        $bills_total_HT = round($order->total_paid_tax_excl, 2);
        $bills_total_TVA = round($order->total_paid_tax_incl, 2);
        $bills_total_TTC = round($order->total_paid, 2);

        /* declaration et ecriture des titres des colonnes pour elvetis */
        $elvetis_column_title = array(
            'Nom du client', 'Adresse 1', 'Adresse2', 'Code postal', 'Ville', 'Adresse email',
            'Téléphone 1', 'Téléphone 2', 'Numéro de facture', 'Montant Total HT de la commande', 'Montant Total HT TVA de la commande', 'Montant TTC de la commande', 'Identifiant relais colis',
            'Mode de paiement', 'Pays ', 'Code transport', 'Filler', 'Remise coupon', 'Adresse complémentaire', 'Commentaire sur transport', 'S = livraison le Samedi', 'Code produit Colissimo',
            'Code postal Export', 'N° téléphone Export', 'CIP (code produit)', 'Quantité', 'Prix unitaire HT', 'Prix unitaire TTC'
        );

        /* boucle permettant de recuperer les produits de la commande */
        foreach ($products as $product) :

            /* information produit */
            $product_id = $product['id_product'];
            $product_reference = $product['product_reference'];
            $product_quantity =  $product['product_quantity'];
            $product_unit_HT = round($product['unit_price_tax_excl'], 2);
            $product_unit_TTC = round($product['unit_price_tax_incl'], 2);

            /* recuperation de l'isbn parent du produits */
            $product_isbn = $product['isbn'];

            /* la valeur 1 correspond a l'organisme elvetis */
            if ($product_isbn == 1) :
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

                for ($i = $first_keys; $i <= $last_keys; $i++) :
                    if (!isset($elvetis_array_temp[$i])) {
                        $elvetis_array_temp[$i] = "";
                    }
                endfor;

                /* ordonner le tableau*/
                ksort($elvetis_array_temp);

                $elvetis_array_final[] = array_combine($elvetis_column_title, $elvetis_array_temp);
            endif;
        endforeach;

        /* si le tableau elvetis n'est pas vide on ecrit dans un fichier csv */
        if (!empty($elvetis_array_final)) :

            /* ecriture dans notre csv */
            $csv_file_name = 'CDE_' . $bills_id . '.csv';
            $csvfile = __DIR__ . '/tmp/' . $csv_file_name;
            $file = fopen($csvfile, 'w');
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, $elvetis_column_title, ";");
            foreach ($elvetis_array_final as $line => $value) :
                fputcsv($file, $elvetis_array_final[$line], ";");
            endforeach;
            $this->elvetisSendOrder($csvfile, $csv_file_name);
        endif;
    }
}
