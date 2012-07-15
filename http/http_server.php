<?php 
/**
 * Jaxl (Jabber XMPP Library)
 *
 * Copyright (c) 2009-2012, Abhinav Singh <me@abhinavsingh.com>.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 * * Redistributions of source code must retain the above copyright
 * notice, this list of conditions and the following disclaimer.
 *
 * * Redistributions in binary form must reproduce the above copyright
 * notice, this list of conditions and the following disclaimer in
 * the documentation and/or other materials provided with the
 * distribution.
 *
 * * Neither the name of Abhinav Singh nor the names of his
 * contributors may be used to endorse or promote products derived
 * from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRIC
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 */

require_once JAXL_CWD.'/http/http_request.php';

class HTTPServer {
	
	private $server = null;
	private $cb = null;
	
	private $requests = array();
	
	public function __construct($port=9699, $address="127.0.0.1") {
		$path = 'tcp://'.$address.':'.$port;
		$this->server = new JAXLSocketServer($path, array(&$this, 'on_request'), array(&$this, 'on_accept'));
	}
	
	public function __destruct() {
		$this->server = null;
	}
	
	public function start($cb) {
		$this->cb = $cb;
		JAXLLoop::run();
	}
	
	public function on_accept($sock, $addr) {
		$request = new HTTPRequest($sock, $addr);
		$this->requests[$sock] = &$request;
	}
	
	public function on_request($sock, $addr, $raw) {
		$request = $this->requests[$sock];
		$lines = explode(PHP_EOL, $raw);
		
		if($request->state() == 'wait_for_request_line') {
			// request line
			list($method, $resource, $version) = explode(" ", $lines[0]);
			unset($lines[0]);
			$request->line($method, $resource, $version);
		}
		
		// headers
		//print_r($lines);
		foreach($lines as $line) {
			$line_parts = explode(":", $line);
			if(sizeof($line_parts) > 1) {
				if(strlen($line_parts[0]) > 0) {
					$k = $line_parts[0];
					unset($line_parts[0]);
					$v = implode(":", $line_parts);
					$request->set_header($k, $v);
				}
			}
			else if(strlen(trim($line_parts[0])) == 0) {
				$request->empty_line();
			}
			else {
				$request->body($line);
			}
		}
		
		if($request->state() == 'request_received') {
			if($this->cb) 
				call_user_func($this->cb, $request);
		}
		else {
			// reactivate read
			$this->server->read($sock);
		}
	}
	
	public function read($request) {
		$this->server->read($request->sock);
	}
	
	public function send($request, $code, $body='', $headers=array()) {
		
	}
	
	public function ok($request, $body='') {
		$this->send($request, 200, $body);
	}
	
	public function close($request) {
		$this->server->close($request->sock);
	}
	
}

?>