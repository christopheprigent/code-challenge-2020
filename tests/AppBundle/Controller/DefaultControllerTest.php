<?php

namespace Tests\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DefaultControllerTest extends WebTestCase
{
    public function testIndex()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/');

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertContains('Code Challenge', $crawler->filter('.jumbotron .container p')->text());
        $this->assertContains('See the rules', $crawler->filter('.jumbotron .container a.btn')->text());
    }
}
