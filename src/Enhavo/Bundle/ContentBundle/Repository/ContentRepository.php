<?php

/**
 * ContentRepository.php
 *
 * @since 16/05/16
 * @author gseidel
 */

namespace Enhavo\Bundle\ContentBundle\Repository;

use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Enhavo\Bundle\ContentBundle\Entity\Content;

class ContentRepository extends EntityRepository
{
    public function filterByDate($year = 0, $month = 0, $limit = 0)
    {
        $month = intval($month);
        $year = intval($year);
        $limit = intval($limit);

        $query = $this->createQueryBuilder('n');
        $query->andWhere('n.public = true');

        if($year >= 1970 && $month > 0 && $month < 13) {
            $monthEnd = new \DateTime(sprintf('%s-%s-01 23:59:59', $year, $month));
            $query->Where('n.publicationDate >= :monthStart');
            $query->AndWhere('n.publicationDate <= :monthEnd');
            $query->setParameter('monthStart', new \DateTime(sprintf('%s-%s-01 00:00:00', $year, $month)));
            $query->setParameter('monthEnd', $monthEnd->modify('last day of this month'));
        } elseif($year >= 1970) {
            $query->Where('n.publicationDate >= :yearStart');
            $query->AndWhere('n.publicationDate <= :yearEnd');
            $query->setParameter('yearStart', new \DateTime(sprintf('%s-01-01 00:00:00', $year)));
            $query->setParameter('yearEnd', new \DateTime(sprintf('%s-12-31 23:59:59', $year)));
        } else {
            $query->orderBy('n.sticky','desc');
        }

        $query->andWhere('n.publicationDate <= :currentDate');
        $query->setParameter('currentDate', new \DateTime());
        if($limit > 0) {
            $query->setMaxResults($limit);
        }

        $query->orderBy('n.publicationDate','desc');
        return $query->getQuery()->getResult();
    }

    public function getMonths()
    {
        $query = $this->createQueryBuilder('n');
        $query->andWhere('n.public = true');
        $query->andWhere('n.publicationDate <= :currentDate');
        $query->setParameter('currentDate', new \DateTime());
        $query->orderBy('n.publicationDate','desc');
        $content = $query->getQuery()->getResult();

        $tmpDates = array();

        /** @var $item Content */
        foreach($content as $item) {
            if($item->getPublicationDate()) {
                $tmpDates[] = $item->getPublicationDate()->format('Y-m');
            }
        }
        $tmpDates = array_unique($tmpDates);
        $dates = array();
        foreach($tmpDates as $date) {
            $dates[] = new \DateTime(sprintf('%s-01', $date));
        }

        return $dates;
    }

    public function findPublished()
    {
        $query = $this->createQueryBuilder('n');
        $query->andWhere('n.public = true');
        $query->andWhere('n.publicationDate <= :currentDate');
        $query->setParameter('currentDate', new \DateTime());
        $query->orderBy('n.publicationDate','desc');
        $contents = $query->getQuery()->getResult();

        return $contents;
    }

    /**
     * Returns the next published content in order if sorted by publication date.
     * Only returns contents that are published and whose publication date does not lie in the future.
     * The order used is the same as in findPublished().
     *
     * @param Content $currentContent The current content
     * @param \DateTime $currentDate The date used to determine if a publication date lies in the future. If null or omitted, today is used.
     * @return null|Content The next content after $currentContent, or null if $currentContent is the last one.
     */
    public function findNextInDateOrder(Content $currentContent, \DateTime $currentDate = null)
    {
        if (null === $currentDate) {
            $currentDate = new \DateTime();
        }

        // Find contents with same date
        $query = $this->createQueryBuilder('n');
        $query->andWhere('n.public = true');
        $query->andWhere('n.publicationDate <= :currentDate');
        $query->andWhere('n.publicationDate = :contentDate');
        $query->setParameter('currentDate', $currentDate);
        $query->setParameter('contentDate', $currentContent->getPublicationDate());
        $contentsSameDate = $query->getQuery()->getResult();
        if (!empty($contentsSameDate) && !($contentsSameDate[0]->getId() == $currentContent->getId())) {
            // Return previous in list
            for($i = 0; $i < count($contentsSameDate); $i++) {
                if ($contentsSameDate[$i]->getId() == $currentContent->getId()) {
                    return $contentsSameDate[$i - 1];
                }
            }
        }

        // No next one at current date, search for newer ones
        $query = $this->createQueryBuilder('n');
        $query->andWhere('n.public = true');
        $query->andWhere('n.publicationDate <= :currentDate');
        $query->andWhere('n.publicationDate > :contentDate');
        $query->setParameter('currentDate', $currentDate);
        $query->setParameter('contentDate', $currentContent->getPublicationDate());
        $query->addOrderBy('n.publicationDate','asc');
        $query->addOrderBy('n.id','desc');
        $query->setMaxResults(1);
        $nextContent = $query->getQuery()->getResult();
        if (empty($nextContent)) {
            // No newer contents
            return null;
        } else {
            return $nextContent[0];
        }
    }

    /**
     * Returns the previous published content in order if sorted by publication date.
     * Only returns contents that are published and whose publication date does not lie in the future.
     * The order used is the same as in findPublished().
     *
     * @param Content $currentContent The current content
     * @param \DateTime $currentDate The date used to determine if a publication date lies in the future. If null or omitted, today is used.
     * @return null|Content The previous content before $currentContent, or null if $currentContent is the first one.
     */
    public function findPreviousInDateOrder(Content $currentContent, \DateTime $currentDate = null)
    {
        if (null === $currentDate) {
            $currentDate = new \DateTime();
        }

        // Find contents with same date
        $query = $this->createQueryBuilder('n');
        $query->andWhere('n.public = true');
        $query->andWhere('n.publicationDate <= :currentDate');
        $query->andWhere('n.publicationDate = :contentDate');
        $query->setParameter('currentDate', $currentDate);
        $query->setParameter('contentDate', $currentContent->getPublicationDate());
        $contentsSameDate = $query->getQuery()->getResult();
        if (!empty($contentsSameDate) && !($contentsSameDate[count($contentsSameDate) - 1]->getId() == $currentContent->getId())) {
            // Return next in list
            for($i = 0; $i < count($contentsSameDate); $i++) {
                if ($contentsSameDate[$i]->getId() == $currentContent->getId()) {
                    return $contentsSameDate[$i + 1];
                }
            }
        }

        // No next one at current date, search for newer ones
        $query = $this->createQueryBuilder('n');
        $query->andWhere('n.public = true');
        $query->andWhere('n.publicationDate <= :currentDate');
        $query->andWhere('n.publicationDate < :contentDate');
        $query->setParameter('currentDate', $currentDate);
        $query->setParameter('contentDate', $currentContent->getPublicationDate());
        $query->addOrderBy('n.publicationDate','desc');
        $query->addOrderBy('n.id','asc');
        $query->setMaxResults(1);
        $nextContent = $query->getQuery()->getResult();
        if (empty($nextContent)) {
            // No older contents
            return null;
        } else {
            return $nextContent[0];
        }
    }
}