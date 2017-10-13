<?php

class ServerController extends AppController
{

    public $components = array('Session');

    public function admin_link()
    {
        if (!$this->isConnected || !$this->Permissions->can('MANAGE_SERVERS'))
            throw new ForbiddenException();
        $this->layout = "admin";
        $this->set('title_for_layout', $this->Lang->get('SERVER__LINK'));

        $this->loadModel('Server');
        $servers = $this->Server->find('all');
        $banner_server = unserialize($this->Configuration->getKey('banner_server'));

        if ($banner_server) {
            foreach ($servers as $key => $value) {
                if (in_array($value['Server']['id'], $banner_server))
                    $servers[$key]['Server']['activeInBanner'] = true;
                else
                    $servers[$key]['Server']['activeInBanner'] = false;
            }
        }

        foreach ($servers as $key => $value)
            $servers[$key]['Server']['data'] = json_decode($servers[$key]['Server']['data'], true);

        $bannerMsg = $this->Lang->get('SERVER__STATUS_MESSAGE');

        $this->set(compact('servers', 'bannerMsg'));
        $this->set('isEnabled', $this->Configuration->getKey('server_state'));
        $this->set('isCacheEnabled', $this->Configuration->getKey('server_cache'));
        $this->set('timeout', $this->Configuration->getKey('server_timeout'));
    }

    public function admin_editBannerMsg()
    {
        $this->autoRender = false;
        $this->response->type('json');

        if ($this->isConnected AND $this->Permissions->can('MANAGE_SERVERS')) {

            if ($this->request->is('ajax')) {

                $this->Lang->set('SERVER__STATUS_MESSAGE', $this->request->data['msg']);

                $this->response->body(json_encode(array('statut' => true, 'msg' => $this->Lang->get('SERVER__EDIT_BANNER_MSG_SUCCESS'))));

            } else {
                throw new NotFoundException();
            }

        }
    }

    public function admin_switchState()
    {
        if ($this->isConnected AND $this->Permissions->can('MANAGE_SERVERS')) {

            $this->autoRender = false;

            $value = ($this->Configuration->getKey('server_state')) ? 0 : 1;

            $this->Configuration->setKey('server_state', $value);

            $this->Session->setFlash($this->Lang->get('SERVER__SUCCESS_SWITCH'), 'default.success');
            $this->redirect(array('action' => 'link'));

        } else {
            $this->redirect('/');
        }
    }

    public function admin_switchCacheState()
    {
        if ($this->isConnected AND $this->Permissions->can('MANAGE_SERVERS')) {

            $this->autoRender = false;

            $value = ($this->Configuration->getKey('server_cache')) ? 0 : 1;

            $this->Configuration->setKey('server_cache', $value);

            $this->Session->setFlash($this->Lang->get('SERVER__SUCCESS_CACHE_SWITCH'), 'default.success');
            $this->redirect(array('action' => 'link'));

        } else {
            $this->redirect('/');
        }
    }

    public function admin_switchBanner($id = false)
    {
        $this->autoRender = false;
        if ($this->isConnected && $this->Permissions->can('MANAGE_SERVERS')) {
            if ($id) {

                $banner = unserialize($this->Configuration->getKey('banner_server'));

                if ($banner) {

                    if (in_array($id, $banner)) {
                        unset($banner[array_search($id, $banner)]);
                    } else {
                        $banner[] = $id;
                    }

                    $banner = array_values($banner);

                    $this->Configuration->setKey('banner_server', serialize($banner));

                } else {
                    $this->Configuration->setKey('banner_server', serialize(array($id)));
                }
            }

        } else {
            throw new ForbiddenException();
        }
    }


    public function admin_delete($id = false)
    {
        $this->autoRender = false;
        if ($this->isConnected && $this->Permissions->can('MANAGE_SERVERS')) {
            if ($id) {
                $this->loadModel('Server');
                if ($this->Server->delete($id)) {
                    $this->Session->setFlash($this->Lang->get('SERVER__DELETE_SERVER_SUCCESS'), 'default.success');
                    $this->redirect(array('controller' => 'server', 'action' => 'link', 'admin' => true));
                } else {
                    $this->Session->setFlash($this->Lang->get('ERROR__INTERNAL_ERROR'), 'default.error');
                    $this->redirect(array('controller' => 'server', 'action' => 'link', 'admin' => true));
                }
            } else {
                $this->Session->setFlash($this->Lang->get('ERROR__INTERNAL_ERROR'), 'default.error');
                $this->redirect(array('controller' => 'server', 'action' => 'link', 'admin' => true));
            }
        } else {
            $this->redirect('/');
        }
    }

    public function admin_config()
    {
        $this->autoRender = false;
        $this->response->type('json');
        if ($this->isConnected AND $this->Permissions->can('MANAGE_SERVERS')) {

            $this->layout = null;
            if ($this->request->is('ajax')) {
                if (!empty($this->request->data['timeout'])) {
                    if (filter_var($this->request->data['timeout'], FILTER_VALIDATE_FLOAT)) {
                        $this->Configuration->setKey('server_timeout', $this->request->data['timeout']);

                        $this->response->body(json_encode(array('statut' => true, 'msg' => $this->Lang->get('SERVER__TIMEOUT_SAVE_SUCCESS'))));
                    } else {
                        $this->response->body(json_encode(array('statut' => false, 'msg' => $this->Lang->get('SERVER__INVALID_TIMEOUT'))));
                    }
                } else {
                    $this->response->body(json_encode(array('statut' => false, 'msg' => $this->Lang->get('ERROR__FILL_ALL_FIELDS'))));
                }
            } else {
                throw new NotFoundException();
            }
        } else {
            throw new ForbiddenException();
        }
    }

    public function admin_link_ajax()
    {
        $this->autoRender = false;
        $this->response->type('json');

        if (!$this->isConnected AND $this->Permissions->can('MANAGE_SERVERS'))
            return $this->redirect('/');
        if (!$this->request->is('ajax'))
            return $this->response->body(json_encode(array('statut' => false, 'msg' => $this->Lang->get('ERROR__BAD_REQUEST'))));
        if (empty($this->request->data['host']) || empty($this->request->data['port']) || empty($this->request->data['name']) || !isset($this->request->data['type']))
            return $this->response->body(json_encode(array('statut' => false, 'msg' => $this->Lang->get('ERROR__FILL_ALL_FIELDS'))));

        // default plugin, make the handshake
        if ($this->request->data['type'] == 0) {
            $timeout = $this->Configuration->getKey('server_timeout');
            if (empty($timeout))
                return $this->response->body(json_encode(array('statut' => false, 'msg' => $this->Lang->get('SERVER__TIMEOUT_UNDEFINED'))));

            if (!$this->Server->check('connection', array('host' => $this->request->data['host'], 'port' => $this->request->data['port'], 'timeout' => $timeout)))
                return $this->response->body(json_encode(array('statut' => false, 'msg' => $this->Lang->get('SERVER__LINK_ERROR_' . $this->Server->linkErrorCode))));
            // get secretKey
            $secretKey = $this->Server->getSecretKey();
        } // use simple ping to retrieve data from MC protocol
        else if ($this->request->data['type'] == 1) {
            if (!$this->Server->ping(array('ip' => $this->request->data['host'], 'port' => $this->request->data['port'])))
                return $this->response->body(json_encode(array('statut' => false, 'msg' => $this->Lang->get('SERVER__LINK_FAILED'))));
        } else if ($this->request->data['type'] == 2) {
            if (!$this->Server->rcon(array('ip' => $this->request->data['host'], 'port' => $this->request->data['server_data']['rcon_port'], 'password' => $this->request->data['server_data']['rcon_password']), 'say ' . $this->Lang->get('SERVER__LINK_SUCCESS')))
                return $this->response->body(json_encode(array('statut' => false, 'msg' => $this->Lang->get('SERVER__LINK_FAILED'))));
        } else {
            return $this->response->body(json_encode(array('statut' => false, 'msg' => $this->Lang->get('ERROR__FILL_ALL_FIELDS'))));
        }

        // save the server inside the database/conf
        $this->Configuration->setKey('server_state', 1);
        $id = !empty($this->request->data['id']) ? $this->request->data['id'] : null;

        $this->loadModel('Server');
        $this->Server->read(null, $id);
        $this->Server->set(array(
            'name' => $this->request->data['name'],
            'ip' => $this->request->data['host'],
            'port' => $this->request->data['port'],
            'type' => $this->request->data['type'],
            'data' => json_encode($this->request->data['server_data'])
        ));
        $this->Server->save();

        if ($this->request->data['type'] == 0 && isset($secretKey) && $secretKey)
            $this->Configuration->setKey('server_secretkey', $secretKey);

        return $this->response->body(json_encode(array('statut' => true, 'msg' => $this->Lang->get('SERVER__LINK_SUCCESS'))));
    }

    public function admin_banlist($server_id = false)
    {
        $this->layout = 'admin';
        if (!$this->isConnected || !$this->Permissions->can('MANAGE_SERVERS'))
            throw new ForbiddenException();

        $call = $this->Server->call('GET_BANNED_PLAYERS', $server_id);
        $list = array();
        if (isset($call['GET_BANNED_PLAYERS']) && $call['GET_BANNED_PLAYERS'] !== 'NOT_FOUND')
            foreach ($call['GET_BANNED_PLAYERS'] as $player)
                $list[] = $player;
        $this->set(compact('list'));

        $this->loadModel('Server');
        $this->set('servers', $this->Server->find('all', array('conditions' => array('type' => 0))));

        $this->set('title_for_layout', $this->Lang->get('SERVER__BANLIST'));
    }

    public function admin_whitelist($server_id = false)
    {
        $this->layout = 'admin';
        if (!$this->isConnected || !$this->Permissions->can('MANAGE_SERVERS'))
            throw new ForbiddenException();

        $call = $this->Server->call('GET_BANNED_PLAYERS', $server_id);
        $list = array();
        if (isset($call['GET_WHITELISTED_PLAYERS']) && $call['GET_WHITELISTED_PLAYERS'] !== 'NOT_FOUND')
            foreach ($call['GET_WHITELISTED_PLAYERS'] as $player)
                $list[] = $player;
        $this->set(compact('list'));

        $this->loadModel('Server');
        $this->set('servers', $this->Server->find('all', array('conditions' => array('type' => 0))));

        $this->set('title_for_layout', $this->Lang->get('SERVER__WHITELIST'));
    }

    public function admin_online($server_id = false)
    {
        $this->layout = 'admin';
        if (!$this->isConnected || !$this->Permissions->can('MANAGE_SERVERS'))
            throw new ForbiddenException();

        $call = $this->Server->call('GET_PLAYER_LIST', $server_id);
        $list = array();
        if (isset($call['GET_PLAYER_LIST']) && $call['GET_PLAYER_LIST'] !== 'NOT_FOUND')
            foreach ($call['GET_PLAYER_LIST'] as $player)
                $list[] = $player;
        $this->set(compact('list'));

        $this->loadModel('Server');
        $this->set('servers', $this->Server->find('all', array('conditions' => array('type' => 0))));

        $this->set('title_for_layout', $this->Lang->get('SERVER__STATUS_ONLINE'));
    }

}
