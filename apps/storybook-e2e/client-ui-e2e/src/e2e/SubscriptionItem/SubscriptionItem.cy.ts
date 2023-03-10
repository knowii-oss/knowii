describe('client-ui: SubscriptionItem component', () => {
  beforeEach(() => cy.visit('/iframe.html?id=subscriptionitem--primary'));

  it('should render the component', () => {
    cy.get('h1').should('contain', 'Welcome to SubscriptionItem!');
  });
});
