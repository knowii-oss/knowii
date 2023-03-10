describe('client-ui: SubscriptionTable component', () => {
  beforeEach(() => cy.visit('/iframe.html?id=subscriptiontable--primary'));

  it('should render the component', () => {
    cy.get('h1').should('contain', 'Welcome to SubscriptionTable!');
  });
});
