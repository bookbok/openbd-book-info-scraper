<?php
/**
 * kentoka/openbd-book-info-scraper
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.
 * Redistributions of files must retain the above copyright notice.
 *
 * @author      Kento Oka <kento-oka@kentoka.com>
 * @copyright   (c) Kento Oka
 * @license     MIT
 * @since       1.0.0
 */
namespace Kentoka\BookScraper\OpenBD;

use Kentoka\BookInfoScraper\AbstractIsbnScraper;
use Kentoka\BookInfoScraper\Exception\DataProviderException;
use Kentoka\BookInfoScraper\Information\BookInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 *
 */
class OpenBDScraper extends AbstractIsbnScraper{

    private const AUTHOR_ROLE_LIST  = [
        "A01" => "著",
        "A03" => "脚本",
        "A06" => "作曲",
        "B01" => "編集",
        "B20" => "監修",
        "B06" => "翻訳",
        "A12" => "イラスト",
        "A38" => "原著",
        "A10" => "企画・原案",
        "A08" => "写真",
        "A21" => "解説",
        "E07" => "朗読",
    ];

    private const API_URI    = "https://api.openbd.jp/v1/get";

    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var RequestFactoryInterface
     */
    private $requestFactory;

    /**
     * @var string[]
     */
    private $authorRoleList = self::AUTHOR_ROLE_LIST;

    /**
     * Constructor.
     *
     * @param ClientInterface         $client
     * @param RequestFactoryInterface $requestFactory
     */
    public function __construct(
        ClientInterface $client,
        RequestFactoryInterface $requestFactory
    ){
        $this->client           = $client;
        $this->requestFactory   = $requestFactory;
    }

    /**
     * 著者役割文字列を取得する
     *
     * @param string $code
     *
     * @return string|null
     */
    public function getAuthorRoleText(string $code): ?string{
        return $this->authorRoleList[$code] ?? null;
    }

    /**
     * 著者役割文字列を設定する
     *
     * @param string $code
     * @param string $text
     *
     * @return $this
     */
    public function setAuthorRoleText(string $code, string $text): self{
        if("" === $text){
            throw new \InvalidArgumentException();
        }

        $this->authorRoleList[$code]    = $text;

        return $this;
    }

    /**
     * 著者役割文字列を規定値にリセットする
     *
     * @return $this
     */
    public function resetAuthorRoleText(): self{
        $this->authorRoleList   = self::AUTHOR_ROLE_LIST;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function scrape(string $id): ?BookInterface{
        try{
            $response   = $this->client->sendRequest(
                $this->requestFactory->createRequest("GET", $this->getApiUri($id))
            );
        }catch(ClientExceptionInterface $e){
            throw new DataProviderException(
                $e->getMessage(),
                $e->getCode(),
                $e
            );
        }

        if(200 !== $response->getStatusCode()){
            return null;
        }

        if(null === ($json = $this->normalizeJsonText($response->getBody()->getContents()))){
            return null;
        }

        $data   = json_decode($json, true);

        if(JSON_ERROR_NONE !== json_last_error()){
            throw new DataProviderException(json_last_error_msg());
        }

        if(!isset($data[0])){
            return null;
        }

        return $this->generateBook($data[0]);
    }

    /**
     * Get OpenBD API uri.
     *
     * @param   string  $isbn
     *
     * @return  string
     */
    protected function getApiUri(string $isbn): string{
        return static::API_URI . "?isbn={$isbn}";
    }

    /**
     * Return normalized json text.
     *
     * OpenBD return invalid json text.
     *
     * @param   string  $json
     *
     * @return  string|null
     */
    protected function normalizeJsonText(string $json): ?string{
        return preg_replace("/[[:cntrl:]]/", "", $json);
    }

    protected function generateBook(array $data): ?BookInterface{
        if("00" !== $data["DescriptiveDetail"]["ProductComposition"] ?? null){
            return null;
        }

        $book   = new OpenBDBook($data);

        $book->setSubTitle($book->get("DescriptiveDetail.TitleElement.Subtitle.content"));

        // Description
        foreach($book->get("CollateralDetail.TextContent") ?? [] as $description){
            if("03" === $description["TextType"]){
                $book->setDescription($description["Text"]);

                break;
            }
        }

        // Cover Image Uri
        foreach($book->get("CollateralDetail.SupportingResource") ?? [] as $resource){
            if("01" === $resource["ResourceContentType"]){
                foreach($resource["ResourceContentType"]["ResourceVersion"] ?? [] as $version){
                    $book->setCoverUri($version["ResourceLink"]);

                    break 2;
                }
            }
        }

        // Page Count
        foreach($book->get("DescriptiveDetail.Extent") ?? [] as $extent){
            if("11" === $extent["ExtentType"]){
                $book->setPageCount((int)$extent["ExtentValue"]);

                break;
            }
        }

        // Author
        $authors    = [];

        foreach($book->get("DescriptiveDetail.Contributor") ?? [] as $author){
            $authors[]  = new OpenBDAuthor(
                $author["PersonName"]["content"],
                array_filter(
                    array_map([$this, "getAuthorRoleText"], $author["ContributorRole"]),
                    "is_string"
                )
            );
        }

        $book->setAuthors($authors);

        // Publisher
        $book->setPublisher($book->get("PublishingDetail.Imprint.ImprintName"));

        // Published Date
        $publishedDate      = null;

        foreach($book->get("PublishingDetail.PublishingDate") ?? [] as $publishedDate){
            if(
                "" === $publishedDate["date"]
                || !in_array($publishedDate["PublishingDateRole"], ["01", "11"])
                || 8 !== strlen($publishedDate["date"])
            ){
                continue;
            }

            $publishedDate  = $publishedDate["PublishingDateRole"];

            if("11" === $publishedDate["PublishingDateRole"]){
                break;
            }
        }

        try{
            $book->setPublishedAt(new \DateTime($publishedDate));
        }catch(\Exception $e){
            throw new \LogicException($e->getMessage(), $e->getCode(), $e);
        }

        // Price
        foreach($book->get("ProductSupply.SupplyDetail.Price") ?? [] as $price){
            if(!in_array($price["PriceType"], ["01", "03"])){
                continue;
            }

            $book->setPrice((int)$price["PriceAmount"]);
            $book->setPriceCode($price["CurrencyCode"]);
        }

        return $book;
    }
}
