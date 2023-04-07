import React from 'react';
import { GetStaticProps } from 'next';
import { useTranslations } from 'next-intl';
import { Layout } from '../components/layout/layout';
import { i18nConfig } from '../../../i18n.config.mjs';
import Link from 'next/link';

export const getStaticProps: GetStaticProps = async (ctx) => {
  const locale = ctx.locale ? ctx.locale : i18nConfig.i18n.defaultLocale;
  const messages = (await import(`../../../libs/common/src/lib/messages/${locale}.json`)).default;

  return {
    props: { messages },
  };
};

// eslint-disable-next-line @typescript-eslint/no-empty-interface
interface TermsOfServicePageProps {}

export function TermsOfServicePage(_props: TermsOfServicePageProps) {
  const t = useTranslations('termsOfServicePage');

  return (
    <Layout
      customMeta={{
        title: t('title'),
      }}
    >
      <div className="flex flex-col text-center px-8 py-12 bg-primary-500 dark:bg-steel-500">
        <h1 className="text-white">{t('title')}</h1>
      </div>
      <div className="flex flex-col px-96 py-6">
        <p>
          <span className="font-bold">LAST UPDATED</span>: 2023-04-08
        </p>
        <br />
        <p>
          Thanks for using the Knowii services (including its website, and mobile and web-based applications, and any other tools, products,
          or services provided by DeveloPassion SRL that link to or reference these Terms) (collectively, the "Services"). The Services are
          provided by DeveloPassion SRL ("DeveloPassion", "Knowii", "we," "our," or "us"), located at 193, rue des Masnuy, 7050, Jurbise,
          Belgium.
        </p>
        <br />
        <p>
          By using our Services, you are agreeing to these Terms of Service ("Terms"). Please read them carefully. Our Services are very
          diverse, so sometimes additional terms or product requirements (including age requirements) may apply. If additional terms or
          conditions are available with or applicable to the relevant Services, then those additional terms become part of your agreement
          with us if you use those Services. By accessing or using the Services, you intend and agree to be legally bound by these Terms.
          You may wish to print or save a local copy of the Terms for your records.
        </p>
        <br />
        <p>
          YOU ACKNOWLEDGE AND AGREE THAT THESE TERMS OF SERVICE LIMIT OUR LIABILITY AND THAT YOU ARE RELEASING US FROM VARIOUS CLAIMS IN
          SECTION 8 AND 9 BELOW. THESE TERMS ALSO CONTAIN A BINDING ARBITRATION PROVISION IN SECTION 13 THAT AFFECT YOUR RIGHTS UNDER THESE
          TERMS WITH RESPECT TO THE SERVICES.
        </p>
        <br />
        <h2>1. Using our Services</h2>
        <p>
          Don't misuse our Services. For example, don't interfere with our Services, try to access them using a method other than the
          interface and the instructions that we provide, or extensively or automatically copy any content from the Services (in other
          words, do not try to scrape the content). You may use our Services only as permitted by law, including applicable export and
          re-export control laws and regulations. We may suspend or stop providing our Services to you if you do not comply with our terms
          or policies, if we are investigating suspected misconduct, or for any other reason.
        </p>
        <br />
        <p>
          Using our Services does not give you ownership of any intellectual property rights in our Services or the content you access
          through them ("Content"). You may not use Content, except as permitted in these Terms, by its owner, or as otherwise permitted by
          law. These Terms do not grant you the right to use any branding or logos used in our Services, including the DeveloPassion name
          and logo as well as the Knowii name and logo. Don't remove, obscure, or alter any legal notices displayed in or along with our
          Services.
        </p>
        <br />
        <p>
          Our Services display some Content that is not our own. For example, Content belonging to our advertisers, other third parties,
          you, or other users (collectively, "Third Party Content"). We are not responsible for, and you waive all of our liability with
          respect to, Third Party Content. Third Party Content is the sole responsibility of the individual or entity that makes it
          available to you via the Services. We may review Third Party Content to determine whether it is illegal or violates our policies,
          and we may remove or refuse to display Third Party Content that we believe violates our policies or the law. But we do not
          generally review content beforehand, and we are not obligated to do so.
        </p>
        <br />
        <p>
          In connection with your use of the Services, we may send you service announcements, administrative messages, and other
          information. You may opt out of our marketing emails by clicking on the "unsubscribe" link in marketing e-mails. Please be aware
          that there may be a brief period before we are able to process your opt-out.
        </p>
        <br />
        <p>
          Some of our Services are available on mobile devices, which may cause you to incur data charges with your wireless provider.
          Please be aware that we have no control over these charges, and if you do not wish to be charged, you should stop using the mobile
          features.
        </p>
        <br />
        <h2>2. Your Account</h2>
        <p>
          You may need an account in order to use the Services. If you create your own account, you agree that all registration information
          you give us will be accurate and current. If your account has been assigned to you by an administrator, such as your employer or
          educational institution, different or additional terms may apply and your administrator may be able to access or disable your
          account. You will timely notify us of any changes to any of the foregoing information. You are responsible for controlling access
          to any PCs, mobile devices, or other end points that you allow to store your Services password, or on which you enable a "Remember
          Me" or similar functionality ("Activated Device"). Accordingly, you agree that you will be solely responsible for all activities
          that occur under your Services accounts, including the activities of any individual with whom you share your Services account or
          an Activated Device.
        </p>
        <br />
        <p>
          To protect your account, keep your password confidential. You are responsible for the activity that happens on or through your
          account. If you learn of any unauthorized use of your password, please <Link href="/contact">contact us</Link>.
        </p>
        <br />
        <h2>3. Privacy and Feedback</h2>
        <p>
          Our <Link href="/privacy">privacy policy</Link> explains how we treat your personal information and protect your privacy when you
          use our Services. By using our Services, you agree that we can collect, use, and share data from you as described in our privacy
          policy. We are not responsible for any information or Content that you share with others via your use of the Services. You assume
          all privacy, security, and other risks associated with providing any information, including personally identifiable information,
          to other users of the Service.
        </p>
        <br />
        <p>
          If you submit feedback or suggestions about our Services, you agree that we may use your feedback or suggestions without
          obligation to you.
        </p>
        <br />
        <h2>4. Content You Submit or Share</h2>
        <p>We strongly recommend that you do not submit medical, financial or other sensitive information to Knowii.</p>
        <br />
        <p>
          You may submit, upload, and share videos, pictures, text and other content to or through the Services ("Your Content"), and in
          doing so you must follow these Terms and the rules and policies referenced in these Terms. You retain ownership of any
          intellectual property rights that you hold in Your Content. In short, what belongs to you stays yours.
        </p>
        <br />
        <p>
          When you upload, submit, or otherwise share Your Content to or through our Services, you give us (and those we work with) a
          royalty-free, worldwide license to use, host, store, reproduce, modify, create derivative works (such as those resulting from
          translations, adaptations or other changes we make so that Your Content works better with our Services), communicate, publish,
          publicly perform, publicly display and distribute Your Content. The rights you grant in this license are for the limited purpose
          of operating, promoting, and improving our Services, and to develop new ones. This license continues even if you stop using our
          Services. Make sure you have the necessary rights to grant us this license for any content that you submit to our Services.
        </p>
        <br />
        <p>
          You may request that we delete any of Your Content that you submit to the Services by <Link href="/contact">contacting us</Link>.
          To the extent within our control we'll remove Your Content from public display and mark it for future deletion if permitted by
          applicable law; however, it may persist in backup or residual copies for a reasonable period of time (but will not be available to
          other users through the Services). For purposes of clarification, once you submit or share Your Content with others via the
          Services (e.g., other users or third parties), we no longer have control over those portions of Your Content and will not be able
          to delete it or prevent them from using it.
        </p>
        <br />
        <p>You agree that you will not use the Services to:</p>
        <br />
        <ul className="list-disc pl-6">
          <li>Violate law or a third-party's rights;</li>
          <li>Submit excessive or unsolicited commercial messages or spam any users;</li>
          <li>Submit malicious content or viruses;</li>
          <li>Solicit other people's login information, credit card numbers, or other sensitive information;</li>
          <li>Harass or bully other users; or</li>
          <li>
            Post content that is hate speech, threatening or pornographic, that incites violence or that contains nudity or graphic or
            gratuitous violence.
          </li>
        </ul>
        <br />
        <h2>5. Intellectual Property Protection</h2>
        <p>
          As we ask others to respect our intellectual property rights, we respect the intellectual property rights of others, and require
          our users and customers to do so. If you are a copyright owner or its agent and believe that any content residing on or accessible
          through the Services infringes upon your copyrights, you may submit a notification under the Digital Millennium Copyright Act
          ("DMCA") by providing our Copyright Agent (the "Designated Agent") with the following information in writing (see 17 U.S.C §
          512(c)(3) for further detail):
        </p>
        <br />
        <ul className="list-disc pl-6">
          <li>Identification of the work or material being infringed.</li>
          <li>
            Identification of the material that is claimed to be infringing, including its location, with sufficient detail so that we are
            capable of finding it and verifying its existence.
          </li>
          <li>
            Contact information for the notifying party (the "Notifying Party"), including name, address, telephone number, and email
            address.
          </li>
          <li>
            A statement that the Notifying Party has a good faith belief that the material is not authorized by the copyright owner, its
            agent or law.
          </li>
          <li>
            A statement made under penalty of perjury that the information provided in the notice is accurate and that the Notifying Party
            is authorized to make the complaint on behalf of the copyright owner.
          </li>
          <li>
            A physical or electronic signature of a person authorized to act on behalf of the owner of the copyright that has been allegedly
            infringed.
          </li>
        </ul>
        <br />
        <p>
          Please also note that the information provided in a notice of copyright infringement may be forwarded to the user who posted the
          allegedly infringing content. After removing material in response to a valid DMCA notice, we will notify the user responsible for
          the allegedly infringing material that we have removed or disabled access to the material. We will terminate, under appropriate
          circumstances, users who are repeat copyright infringers, and we reserve the right, in our sole discretion, to terminate any user
          for actual or apparent copyright infringement.
        </p>
        <br />
        <p>
          If you believe you are the wrongful subject of a DMCA notification, you may file a counter-notification with us by providing the
          following information to the Designated Agent at the address below:
        </p>
        <br />
        <ul className="list-disc pl-6">
          <li>The specific URLs of material that we have removed or to which we have disabled access.</li>
          <li>Your name, address, telephone number, and email address.</li>
          <li>
            A statement that you consent to the jurisdiction of Belgian courts, and that you will accept service of process from the person
            who provided the original DMCA notification or an agent of such person.
          </li>
          <li>
            The following statement: "I swear, under penalty of perjury, that I have a good faith belief that the material was removed or
            disabled as a result of a mistake or misidentification of the material to be removed or disabled."
          </li>
          <li>Your signature.</li>
        </ul>
        <br />
        <p>
          Upon receipt of a valid counter-notification, we will forward it to Notifying Party who submitted the original DMCA notification.
          The original Notifying Party (or the copyright holder he or she represents) will then have ten (10) days to notify us that he or
          she has filed legal action relating to the allegedly infringing material. If we do not receive any such notification within ten
          (10) days, we may restore the material to the Services.
        </p>
        <br />
        <p>The contact information for our Designated Agent is:</p>
        <br />
        <p>DeveloPassion SRL Attention: Copyright Agent. 193, rue des Masnuy, 7050 Jurbise, Belgium</p>
        <br />
        <p>
          If you believe that any of your intellectual property rights other than copyrights have been infringed, please{' '}
          <Link href="/contact">contact us</Link>. We reserve the right, in our sole and absolute discretion, to suspend or terminate any
          user who infringes the intellectual property rights of DeveloPassion or others, and/or to remove, delete, edit or disable access
          to such person's content. You agree that we have no liability for any action taken under this section.
        </p>
        <br />
        <h2>6. Modifying and Terminating our Services</h2>
        <p>
          We are constantly changing and improving our Services. We may add or remove functionalities or features, and we may suspend or
          stop a Service altogether, at any time, without any notice or liability.
        </p>
        <br />
        <p>
          You can stop using our Services at any time, although we'll be sorry to see you go. We may also stop providing Services to you, or
          add or create new limits to our Services, at any time.
        </p>
        <br />
        <p>Sections 7 – 14 will survive termination or expiration of these Terms indefinitely</p>
        <br />
        <h2>7. Our Warranties and Disclaimers.</h2>
        <p>
          OTHER THAN AS EXPRESSLY SET OUT IN THESE TERMS, NEITHER DEVELOPASSION NOR ITS LICENSORS, SUPPLIERS, ADVERTISERS, OR DISTRIBUTORS
          MAKE ANY SPECIFIC PROMISES ABOUT THE SERVICES. FOR EXAMPLE, WE DON'T MAKE ANY COMMITMENTS ABOUT THE CONTENT WITHIN THE SERVICES,
          THE SPECIFIC FUNCTIONS OF THE SERVICES, OR THEIR RELIABILITY, AVAILABILITY, OR ABILITY TO MEET YOUR NEEDS. WE ALSO DO NOT MAKE ANY
          WARRANTIES OR COMMITMENT RELATING TO NON-INFRINGEMENT, FREEDOM FROM VIRUSES OR OTHER HARMFUL CODE, OR ERROR-FREE OR UNINTERRUPTED
          OPERATIONS. WE PROVIDE THE SERVICES AND ALL INFORMATION PROVIDED THROUGH THE SERVICES "AS-IS." ADDITIONALLY, YOU UNDERSTAND AND
          AGREE THAT PRECEDENT IS NOT AN ELECTRONIC STORAGE PROVIDER OR ELECTRONIC HEALTH RECORD PROVIDER, AND THUS YOU SHOULD NOT USE
          PRECEDENT TO STORE IMPORTANT OR SENSITIVE INFORMATION. ANY INFORMATION YOU SUBMIT TO PRECEDENT MAY BE LOST, AND THEREFORE YOU
          SHOULD PROPERLY BACK IT UP ELSEWHERE.
        </p>
        <br />
        <p>
          SOME JURISDICTIONS PROVIDE FOR CERTAIN WARRANTIES, LIKE THE IMPLIED WARRANTY OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE
          AND NON-INFRINGEMENT. TO THE EXTENT PERMITTED BY LAW, WE DISCLAIM ALL WARRANTIES.
        </p>
        <br />
        <p>
          YOU AND YOUR HEIRS, SUCCESSORS, AND ASSIGNS HEREBY FOREVER IRREVOCABLY RELEASE, DISCHARGE, AND HOLD HARMLESS US, OUR AFFILIATES,
          AND OUR AND THEIR SUCCESSORS AND ASSIGNS, AND OUR AND THEIR OFFICERS, DIRECTORS, EMPLOYEES, AND AGENTS (COLLECTIVELY, "RELEASED
          PARTIES") FROM, AND AGREE NOT TO SUE ANY RELEASED PARTY FOR, ANY LIABILITIES, CLAIMS, OBLIGATIONS, SUITS, ACTIONS, DEMANDS,
          EXPENSES, AND DAMAGES WHATSOEVER (COLLECTIVELY, "LIABILITIES") THAT YOU MAY HAVE AGAINST ANY RELEASED PARTY WHETHER EXISTING NOW
          OR IN THE FUTURE, WHETHER KNOWN OR UNKNOWN, ARISING OUT OF OR IN CONNECTION WITH YOUR OR A THIRD PARTY'S CONDUCT RELATED TO USE OF
          THE SERVICES. YOU UNDERSTAND AND ACKNOWLEDGE THAT THE FOREGOING SENTENCE RELEASES AND DISCHARGES ALL LIABILITIES, WHETHER OR NOT
          THEY ARE CURRENTLY KNOWN TO YOU, AND YOU WAIVE YOUR RIGHTS UNDER CALIFORNIA CIVIL CODE SECTION 1542. YOU UNDERSTAND THE MEANING OF
          CALIFORNIA CIVIL CODE SECTION 1542, WHICH READS AS FOLLOWS: "A GENERAL RELEASE DOES NOT EXTEND TO CLAIMS WHICH THE CREDITOR DOES
          NOT KNOW OR SUSPECT TO EXIST IN HIS FAVOR AT THE TIME OF EXECUTING THE RELEASE, WHICH IF KNOWN BY HIM MUST HAVE MATERIALLY
          AFFECTED HIS SETTLEMENT WITH THE DEBTOR." BY AGREEING TO THESE TERMS AND THIS WAIVER, YOU ASSUME ALL RISK ARISING FROM YET UNKNOWN
          CLAIMS.
        </p>
        <br />
        <h2>8. Liability for our Services.</h2>
        <p>
          TO THE EXTENT NOT PROHIBITED BY LAW, DEVELOPASSION (AND ITS OFFICERS, DIRECTORS, EMPLOYEES, AND AGENTS) AND OUR LICENSORS,
          SUPPLIERS, ADVERTISERS, AND DISTRIBUTORS, WILL NOT BE RESPONSIBLE FOR LOST PROFITS, REVENUES, OR DATA, FINANCIAL LOSSES OR
          INDIRECT, SPECIAL, CONSEQUENTIAL, EXEMPLARY, OR PUNITIVE DAMAGES.
        </p>
        <br />
        <p>
          TO THE EXTENT NOT PROHIBITED BY LAW, THE TOTAL LIABILITY OF DEVELOPASSION (AND ITS OFFICERS, DIRECTORS, EMPLOYEES, AND AGENTS) AND
          OUR LICENSORS, SUPPLIERS, ADVERTISERS, AND DISTRIBUTORS, FOR ANY AND ALL CLAIMS UNDER THESE TERMS OR RELATING TO YOUR USE OF THE
          SERVICES, INCLUDING FOR ANY IMPLIED WARRANTIES, IS LIMITED TO THE AMOUNT YOU PAID US TO USE THE SERVICES (OR, IF WE CHOOSE, TO
          SUPPLY YOU THE SERVICES AGAIN).
        </p>
        <br />
        <p>
          IN ALL CASES RELATING TO PROVIDING YOU THE SERVICES, DEVELOPASSION (AND ITS OFFICERS, DIRECTORS, EMPLOYEES, AND AGENTS) AND ITS
          LICENSORS, SUPPLIERS, ADVERTISERS, AND DISTRIBUTORS, WILL NOT BE LIABLE FOR ANY LOSS OR DAMAGE THAT IS NOT REASONABLY FORESEEABLE
          OR THAT IS DUE TO EVENTS OUTSIDE OF OUR REASONABLE CONTROL, SUCH AS WARS, CRIMINAL ACTIVITIES, STORMS, NATURAL DISASTERS, ACTS OF
          GOVERNMENT, SUPPLY INTERRUPTIONS, OR TELECOMMUNICATION OR INTERNET FAILURES.
        </p>
        <br />
        <h2>9. Business/Employer Uses of our Services</h2>
        <p>
          If you are using our Services on behalf of a business or employer, you are accepting these Terms on their behalf, and that
          business or employer agrees to be bound by these Terms.
        </p>
        <br />
        <h2>10. Indemnification</h2>
        <p>
          You hereby agree to indemnify, defend, and hold harmless DeveloPassion, its affiliated companies, and its and their predecessors,
          successors, and assigns, and its and their respective directors, officers, employees, agents, representatives, partners, and
          contractors from and against all claims, losses, expenses, damages and costs (including, but not limited to, reasonable attorneys'
          fees), resulting from or arising out of your actual or alleged breach of these Terms, any Content you provide through the
          Services, or your use or misuse of the Services. However, you will not be responsible for claims, damages, and costs which are
          found by a court of competent jurisdiction to have arisen solely from our violation of applicable law.
        </p>
        <br />
        <h2>11. About these Terms</h2>
        <p>
          We may modify these Terms or any additional terms that apply to a Service for any reason, for example, to reflect changes to the
          law or changes to our Services. You should look at the Terms regularly and the "Last Updated" date at the beginning of these
          Terms. We'll use reasonable efforts to give you notice of these modifications, such as posting notice of modifications to these
          Terms on this web page, through the Services, or via email. By continuing to use the Services after we make these modifications,
          you agree that you will be subject to the modified Terms. If you do not agree to the modified terms for a Service, you should
          discontinue your use of that Service.
        </p>
        <br />
        <p>
          If there is a conflict between these Terms and any additional terms for a Service, the additional terms will control for that
          conflict.
        </p>
        <br />

        <p>
          These Terms control the relationship between DeveloPassion and you. They do not create any third-party beneficiary rights (except
          in the limited case of Section 13. If you do not comply with these Terms, and we don't take action right away, this doesn't mean
          that we are giving up any rights that we may have (such as taking action in the future). If it turns out that a particular term is
          not enforceable, this will not affect any other terms.
        </p>
        <br />
        <p>The laws of Belgium, will apply to any disputes arising out of or relating to these terms or the Services.</p>
        <br />
        <p>
          You may not assign or delegate your rights or obligations relating to these terms or your account for the Services without our
          prior written consent. We may assign these terms or assign or delegate any of our rights or obligations at any time. For
          information about how to contact DeveloPassion, please visit our <Link href="/contact">contact page</Link>.
        </p>
        <br />
        <h2>12. Binding Arbitration</h2>
        <p>Without limiting your waiver and release in Section 9, you agree to the following:</p>
        <br />
        <p>
          a. <span className="font-bold">Purpose</span>. Any and all Disputes (as defined below) involving you and DeveloPassion will be
          resolved through individual arbitration. In arbitration, there is no judge or jury and there is less discovery and appellate
          review than in court. This Section 13 (the "Arbitration Provision") shall be broadly interpreted. Notwithstanding anything to the
          contrary in these Terms, this Section 13 does not apply to an action by either party to enjoin the infringement or misuse of its
          intellectual property rights, including copyright, trademark, patent or trade secret rights.
        </p>
        <br />
        <p>
          b. <span className="font-bold">Definitions</span>. The term "Dispute" means any claim or controversy related to the Services or
          the Software, including but not limited to any and all: (1) claims for relief and theories of liability, whether based in
          contract, tort, fraud, negligence, statute, regulation, ordinance, or otherwise; (2) claims that arose before these Terms or any
          prior agreement; (3) claims that arise after the expiration or termination of these Terms; and (4) claims that are currently the
          subject of purported class action litigation in which you are not a member of a certified class. As used in this Arbitration
          Provision, "DeveloPassion" means DeveloPassion and any of its predecessors, successors, assigns, parents, subsidiaries and
          affiliated companies and each of their respective officers, directors, employees and agents, and "you" means you and any users or
          beneficiaries of your access to the Services or the Software.
        </p>
        <br />
        <p>
          c. <span className="font-bold">Initiation of Arbitration Proceeding/Selection of Arbitrator</span>. The party initiating the
          arbitration proceeding may open a case with the Arbitrator of their choice. You may deliver any required or desired notice to
          DeveloPassion by mail to 193, rue des Masnuy, 7050 Jurbise, Belgium.
        </p>
        <br />
        <p>
          d. <span className="font-bold">Right to Sue in a Belgian court</span>. Notwithstanding anything in this Arbitration Provision to
          the contrary, either you or DeveloPassion may bring an individual action in a Belgian court.
        </p>
        <br />
        <p>
          e. <span className="font-bold">Arbitration Procedures</span>. This Arbitration Provision shall govern. If the parties cannot
          agree, the parties shall mutually petition a Belgian court of appropriate jurisdiction to appoint an arbitration organization that
          will administer a proceeding. A single arbitrator will resolve the Dispute. Unless you and DeveloPassion agree otherwise, any
          arbitration hearing will take place in Jurbise, Belgium. The arbitrator will honor claims of privilege recognized by law and will
          take reasonable steps to protect customer account information and other confidential or proprietary information. The arbitrator
          shall issue a reasoned written decision that explains the arbitrator's essential findings and conclusions. The arbitrator's award
          may be entered in any court having jurisdiction over the parties only if necessary for purposes of enforcing the arbitrator's
          award. An arbitrator's award that has been fully satisfied shall not be entered in any court.
        </p>
        <br />
        <p>
          f. <span className="font-bold">Waiver of Class Actions and Collective Relief</span>. THERE SHALL BE NO RIGHT OR AUTHORITY FOR ANY
          CLAIMS TO BE ARBITRATED OR LITIGATED ON A CLASS ACTION, JOINT OR CONSOLIDATED BASIS OR ON BASES INVOLVING CLAIMS BROUGHT IN A
          PURPORTED REPRESENTATIVE CAPACITY ON BEHALF OF THE GENERAL PUBLIC (SUCH AS A PRIVATE ATTORNEY GENERAL), OTHER SUBSCRIBERS OR
          USERS, OR OTHER PERSONS. THE ARBITRATOR MAY AWARD RELIEF ONLY IN FAVOR OF THE INDIVIDUAL PARTY SEEKING RELIEF AND ONLY TO THE
          EXTENT NECESSARY TO PROVIDE RELIEF WARRANTED BY THAT INDIVIDUAL PARTY'S CLAIM. THE ARBITRATOR MAY NOT CONSOLIDATE MORE THAN ONE
          PERSON'S CLAIMS, AND MAY NOT OTHERWISE PRESIDE OVER ANY FORM OF A REPRESENTATIVE OR CLASS PROCEEDING.
        </p>
        <br />
        <p>
          g. <span className="font-bold">Arbitration Fees and Costs</span>. If your claim seeks more than $75,000 in the aggregate, the
          payment of the Arbitration fees and costs will be your responsibility. If your claims seek less than $75,000 in the aggregate, the
          payment of the Arbitration fees and costs will be DeveloPassion's responsibility. However, if the arbitrator finds that your
          Dispute was frivolous or brought for an improper purpose, the payment of the Arbitration fees and costs will be your
          responsibility and you shall reimburse DeveloPassion for all fees and costs. You may hire an attorney to represent you in
          arbitration. You are responsible for your attorneys' fees and additional costs and may only recover your attorneys' fees and costs
          in the arbitration to the extent that you could in court if the arbitration is decided in your favor. Notwithstanding anything in
          this Arbitration Provision to the contrary, DeveloPassion will pay all fees and costs that it is required by law to pay.
        </p>
        <br />
        <p>
          h. <span className="font-bold">Severability and Waiver of Jury Trial</span>. If any part of subsection (f) of this Arbitration
          Provision is found to be illegal or unenforceable, the entire Arbitration provision will be unenforceable and the Dispute will be
          decided by a court. WHETHER IN COURT OR IN ARBITRATION, YOU AND DeveloPassion AGREE TO WAIVE THE RIGHT TO A TRIAL BY JURY TO THE
          FULLEST EXTENT ALLOWED BY LAW. If any other clause in this Arbitration Provision is found to be illegal or unenforceable, that
          clause will be severed from this Arbitration Provision and the remainder of this Arbitration Provision will be given full force
          and effect.
        </p>
        <br />
        <p>
          i. <span className="font-bold">Continuation</span>. This Arbitration Provision will survive the termination or expiration of these
          Terms.
        </p>
        <br />
      </div>
    </Layout>
  );
}

export default TermsOfServicePage;
