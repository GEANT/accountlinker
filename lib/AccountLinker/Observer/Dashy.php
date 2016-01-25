<?php
class sspmod_accountLinker_AccountLinker_Observer_Dashy implements SplObserver {
 
	public function update( SplSubject $SplSubject ) { 
		$status = $SplSubject->getLastEntity();
		
		SimpleSAML_Logger::debug('Dashy: === START === ');
		SimpleSAML_Logger::debug($this->_request($status));
		SimpleSAML_Logger::debug('Dashy: === END === ');
	} 
	
	private function _request($body) {
		// do your curl, ideally some async request   
	}
 
}

?>